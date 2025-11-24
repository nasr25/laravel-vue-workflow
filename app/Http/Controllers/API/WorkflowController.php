<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\WorkflowPath;
use App\Models\Department;
use Illuminate\Http\Request as HttpRequest;

class WorkflowController extends Controller
{
    /**
     * Check if user has workflow permission
     */
    private function checkWorkflowPermission($user, $permission)
    {
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to perform this action.'
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
                'message' => 'Unauthorized. You do not have permission to view pending requests.'
            ], 403);
        }

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();

        if (!$deptA) {
            return response()->json([
                'message' => 'Department A not found'
            ], 404);
        }

        // Get all requests that are in Department A
        $requests = Request::where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->with(['user', 'currentDepartment', 'workflowPath', 'attachments', 'transitions.actionedBy'])
            ->orderBy('submitted_at', 'asc')
            ->get();

        return response()->json([
            'requests' => $requests
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
                'message' => 'Department A not found'
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
                'message' => 'No steps found in this workflow path'
            ], 400);
        }

        // Update request with workflow path and move to first department
        $userRequest->update([
            'workflow_path_id' => $workflowPath->id,
            'current_department_id' => $firstStep->department_id,
            'status' => 'in_review',
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $firstStep->department_id,
            'actioned_by' => $user->id,
            'action' => 'assign_path',
            'from_status' => 'pending',
            'to_status' => 'in_review',
            'comments' => $validated['comments'] ?? "Assigned to workflow path: {$workflowPath->name}",
        ]);

        return response()->json([
            'message' => 'Request assigned to workflow path successfully',
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
                'message' => 'Department A not found'
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

        $userRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'completed_at' => now(),
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $deptA->id,
            'actioned_by' => $user->id,
            'action' => 'reject',
            'from_status' => $previousStatus,
            'to_status' => 'rejected',
            'comments' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'Request rejected successfully',
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
                'message' => 'Department A not found'
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

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => null,
            'actioned_by' => $user->id,
            'action' => 'request_details',
            'from_status' => $previousStatus,
            'to_status' => 'need_more_details',
            'comments' => $validated['comments'],
        ]);

        return response()->json([
            'message' => 'More details requested from user',
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
                'message' => 'Department A not found'
            ], 404);
        }

        $validated = $request->validate([
            'comments' => 'nullable|string',
        ]);

        $userRequest = Request::where('id', $requestId)
            ->where('current_department_id', $deptA->id)
            ->whereIn('status', ['pending', 'in_review'])
            ->firstOrFail();

        $previousStatus = $userRequest->status;

        $userRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'to_department_id' => $deptA->id,
            'actioned_by' => $user->id,
            'action' => 'complete',
            'from_status' => $previousStatus,
            'to_status' => 'completed',
            'comments' => $validated['comments'] ?? 'Request completed and approved',
        ]);

        return response()->json([
            'message' => 'Request completed successfully',
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
                'message' => 'Department A not found'
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
                'message' => 'No previous department found to return to'
            ], 400);
        }

        $previousDepartmentId = $lastTransition->from_department_id;
        $previousStatus = $userRequest->status;

        $userRequest->update([
            'current_department_id' => $previousDepartmentId,
            'status' => 'in_review',
            'current_user_id' => null, // Unassign from any employee
        ]);

        // Create transition record
        \App\Models\RequestTransition::create([
            'request_id' => $userRequest->id,
            'from_department_id' => $deptA->id,
            'to_department_id' => $previousDepartmentId,
            'actioned_by' => $user->id,
            'action' => 'return_to_department',
            'from_status' => $previousStatus,
            'to_status' => 'in_review',
            'comments' => $validated['comments'],
        ]);

        return response()->json([
            'message' => 'Request returned to previous department for revision',
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
                'message' => 'Department A not found'
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
            'has_evaluated' => $evaluations->count() === $questions->count()
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
            'evaluations.*.answer' => 'required|integer|min:1|max:10',
            'evaluations.*.notes' => 'nullable|string',
        ]);

        $userRequest = Request::findOrFail($requestId);

        // Delete existing evaluations for this request
        \App\Models\RequestEvaluation::where('request_id', $requestId)->delete();

        // Create new evaluations
        foreach ($validated['evaluations'] as $evaluation) {
            $question = \App\Models\EvaluationQuestion::findOrFail($evaluation['question_id']);

            // Calculate score: (answer/10) * weight
            $score = ($evaluation['answer'] / 10) * $question->weight;

            \App\Models\RequestEvaluation::create([
                'request_id' => $requestId,
                'evaluation_question_id' => $evaluation['question_id'],
                'evaluated_by' => $user->id,
                'score' => $score,
                'notes' => $evaluation['notes'] ?? null,
            ]);
        }

        // Calculate total score
        $totalScore = \App\Models\RequestEvaluation::where('request_id', $requestId)->sum('score');

        return response()->json([
            'message' => 'Evaluation submitted successfully',
            'total_score' => round($totalScore, 2)
        ]);
    }
}
