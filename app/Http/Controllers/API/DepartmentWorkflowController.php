<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\Department;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request as HttpRequest;

class DepartmentWorkflowController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get requests assigned to the current department
     */
    public function getDepartmentRequests(HttpRequest $request)
    {
        $user = $request->user();

        // Admin can see all requests
        if ($user->role === 'admin') {
            $requests = Request::whereIn('status', ['pending', 'in_review'])
                ->with(['user', 'currentDepartment', 'workflowPath.steps.department', 'attachments', 'transitions.actionedBy', 'currentAssignee'])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Add evaluation status for each request
            $requests->each(function($request) {
                $request->has_evaluated = true; // Admins don't need to evaluate
                $request->requires_evaluation = false;
                $request->path_evaluations = [];
            });

            return response()->json([
                'requests' => $requests
            ]);
        }

        // Get departments where user is manager or employee
        $userDepartments = $user->departments()->pluck('departments.id');

        if ($userDepartments->isEmpty()) {
            return response()->json([
                'message' => 'You are not assigned to any department'
            ], 403);
        }

        // Get requests in user's departments
        // If user is a manager, show all requests in their department
        // If user is an employee, show all requests (both assigned and unassigned)
        $query = Request::whereIn('current_department_id', $userDepartments)
            ->where('status', 'in_review');

        // Check if user is a manager in any of their departments
        $isManager = $user->departments()
            ->whereIn('departments.id', $userDepartments)
            ->where('department_user.role', 'manager')
            ->exists();

        // Both managers and employees can see all requests in their department
        // This allows employees to see unassigned requests that managers might assign to them
        // Employees can only take action on requests assigned to them (controlled in other methods)

        $requests = $query->with(['user', 'currentDepartment', 'workflowPath.steps.department', 'attachments', 'transitions.actionedBy', 'currentAssignee'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Add evaluation status for each request
        $requests->each(function($request) use ($isManager) {
            if ($isManager && $request->workflow_path_id) {
                // Get evaluation questions for this workflow path
                $questionsCount = \App\Models\PathEvaluationQuestion::where('workflow_path_id', $request->workflow_path_id)
                    ->where('is_active', true)
                    ->count();

                // Get completed evaluations for this request with questions
                $evaluations = \App\Models\PathEvaluation::where('request_id', $request->id)
                    ->with('question')
                    ->get();

                // Request is evaluated if all questions have been answered
                $request->has_evaluated = $questionsCount > 0 && $evaluations->count() === $questionsCount;
                $request->requires_evaluation = $questionsCount > 0;
                $request->path_evaluations = $evaluations;

                // Check if request was previously assigned to an employee
                $lastAssignment = \App\Models\RequestTransition::where('request_id', $request->id)
                    ->where('action', 'assign')
                    ->where('to_department_id', $request->current_department_id)
                    ->latest()
                    ->first();

                $request->was_assigned_to_employee = $lastAssignment !== null;
                $request->last_assigned_user_id = $lastAssignment?->to_user_id ?? null;
            } else {
                $request->has_evaluated = true; // Employees don't need to evaluate
                $request->requires_evaluation = false;
                $request->path_evaluations = [];
                $request->was_assigned_to_employee = false;
                $request->last_assigned_user_id = null;
            }
        });

        return response()->json([
            'requests' => $requests
        ]);
    }

    /**
     * Get employees in the same department (for managers to assign)
     */
    public function getDepartmentEmployees(HttpRequest $request)
    {
        $user = $request->user();

        // Get departments where user is manager
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'You are not a manager of any department'
            ], 403);
        }

        // Get all employees in managed departments
        $employees = User::whereHas('departments', function($query) use ($managedDepartments) {
            $query->whereIn('departments.id', $managedDepartments)
                  ->where('department_user.role', 'employee');
        })->with(['departments' => function($query) use ($managedDepartments) {
            $query->whereIn('departments.id', $managedDepartments);
        }])->get();

        return response()->json([
            'employees' => $employees
        ]);
    }

    /**
     * Assign request to an employee within the department
     */
    public function assignToEmployee($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'comments' => 'nullable|string',
        ]);

        // Verify user is manager in a department
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'Only department managers can assign to employees'
            ], 403);
        }

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $managedDepartments)
            ->where('status', 'in_review')
            ->firstOrFail();

        // Verify employee belongs to the same department
        $employee = User::findOrFail($validated['employee_id']);
        $employeeDepartments = $employee->departments()->pluck('departments.id');

        if (!$employeeDepartments->intersect($managedDepartments)->count()) {
            return response()->json([
                'message' => 'Employee must be in the same department'
            ], 400);
        }

        $userRequest->update([
            'current_user_id' => $employee->id,
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $userRequest->current_department_id,
            'to_user_id' => $employee->id,
            'actioned_by' => $user->id,
            'action' => 'assign',
            'from_status' => 'in_review',
            'to_status' => 'in_review',
            'comments' => $validated['comments'] ?? "Assigned to {$employee->name}",
        ]);

        // Send notifications - notify the assigned employee and all stakeholders
        $this->notificationService->notify(
            $employee,
            NotificationService::TYPE_REQUEST_ASSIGNED,
            'Request Assigned to You',
            "You have been assigned to work on request '{$userRequest->title}'.",
            $userRequest->fresh(['user', 'currentDepartment']),
            ['assigned_by' => $user->name]
        );

        // Also notify other stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_STATUS_CHANGED,
            'Request Assigned to Employee',
            "Request '{$userRequest->title}' has been assigned to {$employee->name} for review.",
            ['assigned_to' => $employee->name]
        );

        // Log request assignment
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'assigned',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Manager assigned request '{$userRequest->title}' to employee {$employee->name}",
        ]);

        return response()->json([
            'message' => 'Request assigned to employee successfully',
            'request' => $userRequest->load(['currentDepartment', 'currentAssignee', 'workflowPath'])
        ]);
    }

    /**
     * Employee returns request to department manager for review
     */
    public function returnToManager($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'comments' => 'required|string',
        ]);

        // Get departments where user is employee
        $userDepartments = $user->departments()->pluck('departments.id');

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $userDepartments)
            ->where('status', 'in_review')
            ->where('current_user_id', $user->id) // Must be assigned to this employee
            ->firstOrFail();

        $previousStatus = $userRequest->status;
        $previousDepartment = $userRequest->current_department_id;

        // Return to manager (unassign from employee, keep in same department)
        $userRequest->update([
            'current_user_id' => null, // Unassign from employee, goes back to manager
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'from_department_id' => $previousDepartment,
            'to_department_id' => $previousDepartment,
            'actioned_by' => $user->id,
            'action' => 'complete',
            'from_status' => $previousStatus,
            'to_status' => 'in_review',
            'comments' => $validated['comments'],
        ]);

        // Send notifications to department managers and stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_STATUS_CHANGED,
            'Request Returned to Manager',
            "Request '{$userRequest->title}' has been returned to the department manager by {$user->name}. Comments: {$validated['comments']}",
            ['returned_by' => $user->name, 'comments' => $validated['comments']]
        );

        return response()->json([
            'message' => 'Request returned to department manager for review',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Manager completes validation and returns to Department A
     */
    public function returnToDepartmentA($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'comments' => 'required|string',
        ]);

        // Get departments where user is manager
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'Only department managers can return requests to Department A'
            ], 403);
        }

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $managedDepartments)
            ->where('status', 'in_review')
            ->whereNull('current_user_id') // Must not be assigned to employee
            ->firstOrFail();

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => 'Department A not found'
            ], 500);
        }

        $previousStatus = $userRequest->status;
        $previousDepartment = $userRequest->current_department_id;

        $userRequest->update([
            'current_department_id' => $deptA->id,
            'current_user_id' => null,
            'status' => 'in_review', // Keep as in_review since it's coming back for validation
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'from_department_id' => $previousDepartment,
            'to_department_id' => $deptA->id,
            'actioned_by' => $user->id,
            'action' => 'complete',
            'from_status' => $previousStatus,
            'to_status' => 'in_review',
            'comments' => $validated['comments'],
        ]);

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_STATUS_CHANGED,
            'Request Returned to Department A',
            "Request '{$userRequest->title}' has been returned to Department A for validation. Comments: {$validated['comments']}",
            ['returned_by' => $user->name, 'comments' => $validated['comments']]
        );

        return response()->json([
            'message' => 'Request returned to Department A for validation',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Get path evaluation questions for a request
     */
    public function getPathEvaluationQuestions($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Get departments where user is manager
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'Only department managers can evaluate requests'
            ], 403);
        }

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $managedDepartments)
            ->with('workflowPath')
            ->firstOrFail();

        // Get evaluation questions for this workflow path
        $questions = \App\Models\PathEvaluationQuestion::where('workflow_path_id', $userRequest->workflow_path_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        // Get existing evaluations
        $evaluations = \App\Models\PathEvaluation::where('request_id', $requestId)
            ->with('question')
            ->get()
            ->keyBy('path_evaluation_question_id');

        return response()->json([
            'questions' => $questions,
            'evaluations' => $evaluations,
            'has_evaluated' => $questions->count() > 0 && $evaluations->count() === $questions->count()
        ]);
    }

    /**
     * Submit path evaluation for a request
     */
    public function submitPathEvaluation($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'evaluations' => 'required|array',
            'evaluations.*.question_id' => 'required|exists:path_evaluation_questions,id',
            'evaluations.*.is_applied' => 'required|boolean',
            'evaluations.*.notes' => 'nullable|string|max:1000'
        ]);

        // Get departments where user is manager
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'Only department managers can evaluate requests'
            ], 403);
        }

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $managedDepartments)
            ->with('workflowPath')
            ->firstOrFail();

        // Save evaluations
        foreach ($validated['evaluations'] as $evaluation) {
            \App\Models\PathEvaluation::updateOrCreate(
                [
                    'request_id' => $requestId,
                    'path_evaluation_question_id' => $evaluation['question_id']
                ],
                [
                    'evaluated_by' => $user->id,
                    'is_applied' => $evaluation['is_applied'],
                    'notes' => $evaluation['notes'] ?? null
                ]
            );
        }

        return response()->json([
            'message' => 'Evaluation submitted successfully'
        ]);
    }

    /**
     * Accept idea for later implementation
     */
    public function acceptIdeaForLater($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'expected_execution_date' => 'required|date|after_or_equal:today',
            'comments' => 'nullable|string',
        ]);

        // Get departments where user is manager
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'Only department managers can accept ideas'
            ], 403);
        }

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $managedDepartments)
            ->where('status', 'in_review')
            ->whereNull('current_user_id')
            ->firstOrFail();

        $previousStatus = $userRequest->status;
        $previousDepartment = $userRequest->current_department_id;

        // Mark as accepted for later - keep in same department but mark differently
        $userRequest->update([
            'status' => 'pending', // Change status to pending for later review
            'expected_execution_date' => $validated['expected_execution_date'],
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'from_department_id' => $previousDepartment,
            'to_department_id' => $previousDepartment,
            'actioned_by' => $user->id,
            'action' => 'accept_later',
            'from_status' => $previousStatus,
            'to_status' => 'pending',
            'comments' => $validated['comments'] ?? 'Idea accepted for future implementation',
        ]);

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_APPROVED,
            'Idea Accepted for Later Implementation',
            "Request '{$userRequest->title}' has been accepted for future implementation. Expected execution date: {$validated['expected_execution_date']}",
            ['expected_execution_date' => $validated['expected_execution_date'], 'comments' => $validated['comments'] ?? '']
        );

        // Log idea acceptance for later
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'approved',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Accepted request '{$userRequest->title}' for later implementation. Expected date: {$validated['expected_execution_date']}",
        ]);

        return response()->json([
            'message' => 'Idea accepted for later implementation',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Reject idea
     */
    public function rejectIdea($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'comments' => 'required|string',
        ]);

        // Get departments where user is manager
        $managedDepartments = $user->departments()
            ->where('department_user.role', 'manager')
            ->pluck('departments.id');

        if ($managedDepartments->isEmpty()) {
            return response()->json([
                'message' => 'Only department managers can reject ideas'
            ], 403);
        }

        $userRequest = Request::where('id', $requestId)
            ->whereIn('current_department_id', $managedDepartments)
            ->where('status', 'in_review')
            ->whereNull('current_user_id')
            ->firstOrFail();

        $previousStatus = $userRequest->status;
        $previousDepartment = $userRequest->current_department_id;

        // Mark as rejected
        $userRequest->update([
            'status' => 'rejected',
            'current_department_id' => null,
            'current_user_id' => null,
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'from_department_id' => $previousDepartment,
            'to_department_id' => null,
            'actioned_by' => $user->id,
            'action' => 'reject_idea',
            'from_status' => $previousStatus,
            'to_status' => 'rejected',
            'comments' => $validated['comments'],
        ]);

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_REJECTED,
            'Idea Rejected',
            "Request '{$userRequest->title}' has been rejected. Comments: {$validated['comments']}",
            ['rejected_by' => $user->name, 'comments' => $validated['comments']]
        );

        // Log idea rejection
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'rejected',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Manager rejected request '{$userRequest->title}'. Comments: {$validated['comments']}",
        ]);

        return response()->json([
            'message' => 'Idea rejected successfully',
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }
}
