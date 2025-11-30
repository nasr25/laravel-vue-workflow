<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\IdeaWorkflowService;
use App\Models\Idea;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    protected $workflowService;

    public function __construct(IdeaWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get pending ideas for logged-in employee
     */
    public function getPendingIdeas(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->isEmployee()) {
                return response()->json([
                    'message' => 'Access denied. Employee role required.'
                ], 403);
            }

            $ideas = $this->workflowService->getPendingIdeasForEmployee($user->id);

            return response()->json([
                'ideas' => $ideas,
                'count' => $ideas->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch pending ideas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve an idea
     */
    public function approve(Request $request, $ideaId)
    {
        $validator = Validator::make($request->all(), [
            'comments' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user->isEmployee()) {
                return response()->json([
                    'message' => 'Access denied. Employee role required.'
                ], 403);
            }

            $idea = Idea::findOrFail($ideaId);

            // Check if employee can approve this idea
            if (!$this->workflowService->canUserApprove($idea, $user->id, 'employee')) {
                return response()->json([
                    'message' => 'You are not authorized to approve this idea at this step.'
                ], 403);
            }

            $this->workflowService->processApproval(
                $idea,
                $user->id,
                'employee',
                $request->input('comments')
            );

            // Reload idea with fresh data
            $idea = Idea::with(['user', 'formType', 'workflowTemplate', 'approvals.department', 'approvals.workflowStep'])
                ->find($ideaId);

            return response()->json([
                'message' => 'Idea approved successfully',
                'idea' => $idea
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve idea',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an idea
     */
    public function reject(Request $request, $ideaId)
    {
        $validator = Validator::make($request->all(), [
            'comments' => 'required|string|max:1000|min:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user->isEmployee()) {
                return response()->json([
                    'message' => 'Access denied. Employee role required.'
                ], 403);
            }

            $idea = Idea::findOrFail($ideaId);

            // Check if employee can reject this idea
            if (!$this->workflowService->canUserApprove($idea, $user->id, 'employee')) {
                return response()->json([
                    'message' => 'You are not authorized to reject this idea at this step.'
                ], 403);
            }

            $this->workflowService->rejectIdeaByApprover(
                $idea,
                $user->id,
                'employee',
                $request->input('comments')
            );

            // Reload idea with fresh data
            $idea = Idea::with(['user', 'formType', 'workflowTemplate', 'approvals.department', 'approvals.workflowStep'])
                ->find($ideaId);

            return response()->json([
                'message' => 'Idea rejected successfully',
                'idea' => $idea
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject idea',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
