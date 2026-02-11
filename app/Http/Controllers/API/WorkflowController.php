<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\WorkflowPath;
use App\Models\Department;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request as HttpRequest;

class WorkflowController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Check if user has workflow permission
     */
    private function checkWorkflowPermission($user, $permission)
    {
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => __('messages.unauthorized_action')
            ], 403);
        }
        return null;
    }

    /**
     * Get all pending requests for Department A review
     */
    public function getPendingRequests(HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if (!$user->hasPermissionTo('workflow.view-pending')) {
            return response()->json([
                'message' => __('messages.unauthorized_view_pending')
            ], 403);
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        // Get all requests that are in Department A
        $requests = Request::where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->with(['user', 'currentDepartment', 'workflowPath', 'attachments.uploader', 'transitions.actionedBy'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        // Get total active evaluation questions count
        $totalQuestions = \App\Models\EvaluationQuestion::where('is_active', true)->count();

        // Add flags to each request
        $requests->each(function($request) use ($totalQuestions) {
            // Check if there was ever an employee assignment in the workflow
            $hadEmployeeAssignment = \App\Models\RequestTransition::where('request_id', $request->id)
                ->where('action', 'assign')
                ->whereNotNull('to_user_id')
                ->exists();

            $request->went_through_employee_processing = $hadEmployeeAssignment;

            // Check if request has been fully evaluated
            $evaluationCount = \App\Models\RequestEvaluation::where('request_id', $request->id)->count();
            $request->has_evaluated = $totalQuestions > 0 && $evaluationCount >= $totalQuestions;
        });

        return response()->json([
            'requests' => $requests
        ]);
    }

    /**
     * Get all requests for Department A managers (all statuses except drafts)
     * Note: Drafts are excluded as they are private to the user who created them
     */
    public function getAllRequests(HttpRequest $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 12);

        // Check permission
        if (!$user->hasPermissionTo('workflow.view-pending')) {
            return response()->json([
                'message' => __('messages.unauthorized_view_requests')
            ], 403);
        }

        // Get all requests with their latest status and current location
        // Exclude draft requests as they are private to the user who created them
        $requests = Request::with([
            'user',
            'currentDepartment',
            'currentAssignee',
            'workflowPath.steps.department',
            'transitions' => function($query) {
                $query->latest()->limit(1);
            },
            'transitions.actionedBy',
            'transitions.toDepartment'
        ])
        ->where('status', '!=', 'draft') // Exclude draft requests
        ->orderBy('updated_at', 'desc')
        ->paginate($perPage);

        return response()->json([
            'requests' => $requests->items(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ]
        ]);
    }

    /**
     * Get request details with full history (excluding drafts)
     * Note: Drafts are excluded as they are private to the user who created them
     */
    public function getRequestDetail($requestId, HttpRequest $request)
    {
        $user = $request->user();

        $requestDetail = Request::with([
            'user',
            'currentDepartment',
            'currentAssignee',
            'workflowPath.steps.department',
            'attachments.uploader',
            'employees',
            'ideaTypes',
            'department',
            'transitions.actionedBy',
            'transitions.toDepartment',
            'transitions.fromDepartment',
            'transitions.toUser',
            'transitions.fromUser',
            'evaluations.question',
            'evaluations.evaluatedBy',
            'pathEvaluations.question',
            'pathEvaluations.evaluatedBy'
        ])
        ->where('status', '!=', 'draft') // Exclude draft requests
        ->findOrFail($requestId);

        // Authorization: Allow access if user is:
        // 1. The request creator
        // 2. Has Department A permission (workflow.view-pending)
        // 3. Is in a department that the request has been/is currently in (manager or employee)
        // 4. Is currently assigned to the request

        $canView = false;

        // Check if user created the request
        if ($requestDetail->user_id === $user->id) {
            $canView = true;
        }

        // Check if user has Department A permission
        else if ($user->hasPermissionTo('workflow.view-pending')) {
            $canView = true;
        }

        // Check if user is currently assigned
        else if ($requestDetail->current_user_id === $user->id) {
            $canView = true;
        }

        // Check if user is in a department involved in this request
        else {
            // Get all departments the user belongs to
            $userDepartments = $user->departments()->pluck('departments.id');

            // Check if request is currently in one of user's departments
            if ($userDepartments->contains($requestDetail->current_department_id)) {
                $canView = true;
            }
            // Check if request has been in one of user's departments (via transitions)
            else {
                $departmentInvolved = \App\Models\RequestTransition::where('request_id', $requestDetail->id)
                    ->where(function($query) use ($userDepartments) {
                        $query->whereIn('from_department_id', $userDepartments)
                            ->orWhereIn('to_department_id', $userDepartments);
                    })
                    ->exists();

                if ($departmentInvolved) {
                    $canView = true;
                }
            }
        }

        if (!$canView) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to view request details.'
            ], 403);
        }

        return response()->json([
            'request' => $requestDetail
        ]);
    }

    /**
     * Get available workflow paths
     */
    public function getWorkflowPaths(HttpRequest $request)
    {
        $paths = WorkflowPath::with(['steps.department'])
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json([
            'paths' => $paths
        ]);
    }

    /**
     * Assign request to a workflow path
     */
    public function assignPath($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.assign-path')) {
            return $error;
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        $validated = $request->validate([
            'workflow_path_id' => 'required|exists:workflow_paths,id',
            'comments' => 'nullable|string',
        ]);

        $userRequest = Request::where('id', $requestId)
            ->where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->firstOrFail();

        $workflowPath = WorkflowPath::with('steps')->findOrFail($validated['workflow_path_id']);

        // Get first step in the workflow path
        $firstStep = $workflowPath->steps()->orderBy('step_order')->first();

        if (!$firstStep) {
            return response()->json([
                'message' => __('messages.no_steps_found')
            ], 400);
        }

        // Update request with workflow path and move to first department
        $userRequest->update([
            'workflow_path_id' => $workflowPath->id,
            'current_department_id' => $firstStep->department_id,
            'status' => 'in_review',
            'current_stage_started_at' => now(),
            'sla_reminder_sent_at' => null,
        ]);

        // Create transition record with auto-generated bilingual comments
        $userComment = $validated['comments'] ?? '';
        $commentsAr = $userComment ? $userComment : "تم التعيين لمسار العمل: {$workflowPath->name}";
        $commentsEn = $userComment ? $userComment : "Assigned to workflow path: {$workflowPath->name}";

        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $firstStep->department_id,
            'actioned_by' => $user->id,
            'action' => 'assign_path',
            'from_status' => 'pending',
            'to_status' => 'in_review',
            'comments_ar' => $commentsAr,
            'comments_en' => $commentsEn,
        ]);

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_ASSIGNED,
            'Request Assigned to Workflow Path',
            "Request '{$userRequest->title}' has been assigned to the workflow path '{$workflowPath->name}' and moved to {$firstStep->department->name}.",
            ['workflow_path' => $workflowPath->name, 'department' => $firstStep->department->name]
        );

        // Log workflow path assignment
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'assigned',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Assigned request '{$userRequest->title}' to workflow path: {$workflowPath->name} and moved to {$firstStep->department->name}",
        ]);

        return response()->json([
            'message' => __('messages.request_assigned_to_path'),
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Reject request (end workflow)
     */
    public function rejectRequest($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.reject-request')) {
            return $error;
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $userRequest = Request::where('id', $requestId)
            ->where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->firstOrFail();

        $previousStatus = $userRequest->status;

        // Store rejection reason in the request's rejection_reason field
        $userRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'completed_at' => now(),
            'current_department_id' => null,
            'current_user_id' => null,
        ]);

        // Create transition record - store user's rejection reason in both language fields
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $deptA->id,
            'actioned_by' => $user->id,
            'action' => 'reject',
            'from_status' => $previousStatus,
            'to_status' => 'rejected',
            'comments_ar' => $validated['rejection_reason'],
            'comments_en' => $validated['rejection_reason'],
        ]);

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_REJECTED,
            'Request Rejected',
            "Request '{$userRequest->title}' has been rejected. Reason: {$validated['rejection_reason']}",
            ['rejection_reason' => $validated['rejection_reason']]
        );

        // Log request rejection
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'rejected',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Rejected request '{$userRequest->title}'. Reason: {$validated['rejection_reason']}",
        ]);

        return response()->json([
            'message' => __('messages.request_rejected'),
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Request more details from user
     */
    public function requestMoreDetails($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.request-details')) {
            return $error;
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        $validated = $request->validate([
            'comments' => 'required|string',
        ]);

        $userRequest = Request::where('id', $requestId)
            ->where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->firstOrFail();

        $previousStatus = $userRequest->status;

        $userRequest->update([
            'status' => 'need_more_details',
            'current_department_id' => null,
        ]);

        // Create transition record - store user's comments in both language fields
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => null,
            'actioned_by' => $user->id,
            'action' => 'request_details',
            'from_status' => $previousStatus,
            'to_status' => 'need_more_details',
            'comments_ar' => $validated['comments'],
            'comments_en' => $validated['comments'],
        ]);

        // Send notifications to request creator
        $this->notificationService->notify(
            $userRequest->user,
            NotificationService::TYPE_REQUEST_STATUS_CHANGED,
            'More Details Requested',
            "More details are needed for your request '{$userRequest->title}'. Please review and resubmit. Comments: {$validated['comments']}",
            $userRequest->fresh(['user', 'currentDepartment']),
            ['comments' => $validated['comments']]
        );

        // Log request for more details
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'requested_details',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Requested more details for request '{$userRequest->title}'. Comments: {$validated['comments']}",
        ]);

        return response()->json([
            'message' => __('messages.more_details_requested'),
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Complete request (final approval)
     */
    public function completeRequest($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.complete-request')) {
            return $error;
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        $validated = $request->validate([
            'comments' => 'nullable|string',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,ppt,pptx|max:10240', // 10MB
        ]);

        $userRequest = Request::where('id', $requestId)
            ->where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->firstOrFail();

        $previousStatus = $userRequest->status;

        $userRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
            'current_department_id' => null,
            'current_user_id' => null,
        ]);

        // Create transition record with auto-generated bilingual comments
        $userComment = $validated['comments'] ?? '';
        $commentsAr = $userComment ? $userComment : 'تم إكمال الطلب والموافقة عليه';
        $commentsEn = $userComment ? $userComment : 'Request completed and approved';

        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $deptA->id,
            'actioned_by' => $user->id,
            'action' => 'complete',
            'from_status' => $previousStatus,
            'to_status' => 'completed',
            'comments_ar' => $commentsAr,
            'comments_en' => $commentsEn,
        ]);

        // Handle file attachments during completion
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');

                \App\Models\RequestAttachment::create([
                    'request_id' => $userRequest->id,
                    'uploaded_by' => $user->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'stage' => 'completed',
                    'uploaded_at' => now(),
                ]);
            }
        }

        // Send notifications to all stakeholders
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_COMPLETED,
            'Request Completed',
            "Request '{$userRequest->title}' has been completed and approved!",
            ['completion_comments' => $commentsEn]
        );

        // Log request completion
        AuditLog::log([
            'user_id' => $user->id,
            'action' => 'completed',
            'model_type' => 'Request',
            'model_id' => $userRequest->id,
            'description' => "Completed and approved request '{$userRequest->title}'",
        ]);

        return response()->json([
            'message' => __('messages.request_completed'),
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Return request to previous department for revision
     */
    public function returnToPreviousDepartment($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.return-request')) {
            return $error;
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        $validated = $request->validate([
            'comments' => 'required|string',
        ]);

        $userRequest = Request::where('id', $requestId)
            ->where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->firstOrFail();

        // Find the previous department from transitions
        // Get the last transition where request was returned to Dept A
        $lastTransition = \App\Models\RequestTransition::where('request_id', $userRequest->id)
            ->where('to_department_id', $deptA->id)
            ->where('action', 'complete')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastTransition || !$lastTransition->from_department_id) {
            return response()->json([
                'message' => __('messages.no_previous_department')
            ], 400);
        }

        $previousDepartmentId = $lastTransition->from_department_id;
        $previousStatus = $userRequest->status;

        $userRequest->update([
            'current_department_id' => $previousDepartmentId,
            'status' => 'in_review',
            'current_user_id' => null, // Unassign from any employee
            'current_stage_started_at' => now(),
            'sla_reminder_sent_at' => null,
        ]);

        // Create transition record - store user's comments in both language fields
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'from_department_id' => $deptA->id,
            'to_department_id' => $previousDepartmentId,
            'actioned_by' => $user->id,
            'action' => 'return_to_department',
            'from_status' => $previousStatus,
            'to_status' => 'in_review',
            'comments_ar' => $validated['comments'],
            'comments_en' => $validated['comments'],
        ]);

        // Send notifications to all stakeholders
        $previousDepartment = Department::find($previousDepartmentId);
        $this->notificationService->notifyRequestStakeholders(
            $userRequest->fresh(['user', 'currentDepartment']),
            NotificationService::TYPE_REQUEST_STATUS_CHANGED,
            'Request Returned for Revision',
            "Request '{$userRequest->title}' has been returned to {$previousDepartment->name} for revision. Comments: {$validated['comments']}",
            ['previous_department' => $previousDepartment->name, 'comments' => $validated['comments']]
        );

        return response()->json([
            'message' => __('messages.request_returned_to_previous'),
            'request' => $userRequest->load(['currentDepartment', 'workflowPath'])
        ]);
    }

    /**
     * Get evaluation questions for a request
     */
    public function getEvaluationQuestions($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.evaluate')) {
            return $error;
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => __('messages.department_a_not_found')
            ], 404);
        }

        // Get active questions
        $questions = \App\Models\EvaluationQuestion::where('is_active', true)
            ->orderBy('order')
            ->get();

        // Get existing evaluations for this request
        $evaluations = \App\Models\RequestEvaluation::where('request_id', $requestId)
            ->with('question')
            ->get()
            ->keyBy('evaluation_question_id');

        return response()->json([
            'questions' => $questions,
            'evaluations' => $evaluations,
            'has_evaluated' => $questions->count() > 0 && $evaluations->count() === $questions->count()
        ]);
    }

    /**
     * Submit evaluation for a request
     */
    public function submitEvaluation($requestId, HttpRequest $request)
    {
        $user = $request->user();

        // Check permission
        if ($error = $this->checkWorkflowPermission($user, 'workflow.evaluate')) {
            return $error;
        }

        $validated = $request->validate([
            'evaluations' => 'required|array',
            'evaluations.*.question_id' => 'required|exists:evaluation_questions,id',
            'evaluations.*.is_applied' => 'required|boolean',
            'evaluations.*.notes' => 'nullable|string',
        ]);

        $userRequest = Request::findOrFail($requestId);

        // Delete existing evaluations for this request
        \App\Models\RequestEvaluation::where('request_id', $requestId)->delete();

        // Create new evaluations
        foreach ($validated['evaluations'] as $evaluation) {
            \App\Models\RequestEvaluation::create([
                'request_id' => $requestId,
                'evaluation_question_id' => $evaluation['question_id'],
                'evaluated_by' => $user->id,
                'is_applied' => $evaluation['is_applied'],
                'notes' => $evaluation['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message' => __('messages.evaluation_submitted')
        ]);
    }
}
