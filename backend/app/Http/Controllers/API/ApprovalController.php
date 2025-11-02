<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Services\IdeaWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApprovalController extends Controller
{
    protected $workflowService;

    public function __construct(IdeaWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get pending ideas for this manager to review
     */
    public function pendingIdeas(Request $request)
    {
        try {
            $ideas = $this->workflowService->getPendingIdeasForManager($request->user()->id);

            return response()->json([
                'success' => true,
                'ideas' => $ideas,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get pending ideas error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve an idea
     */
    public function approve(Request $request, $ideaId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $idea = Idea::findOrFail($ideaId);

            // Verify manager has authority for current step
            $this->verifyManagerAuthority($request->user(), $idea);

            // Sanitize comments
            $comments = $request->comments ? strip_tags(trim($request->comments)) : null;

            $this->workflowService->approveIdea(
                $idea,
                $request->user()->id,
                $comments
            );

            return response()->json([
                'success' => true,
                'idea' => $idea->load('approvals.department', 'approvals.manager'),
                'message' => 'Idea approved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Approve idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an idea
     */
    public function reject(Request $request, $ideaId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'required|string|max:1000|min:3',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $idea = Idea::findOrFail($ideaId);

            // Verify manager has authority for current step
            $this->verifyManagerAuthority($request->user(), $idea);

            // Sanitize comments
            $comments = strip_tags(trim($request->comments));

            $this->workflowService->rejectIdea(
                $idea,
                $request->user()->id,
                $comments
            );

            return response()->json([
                'success' => true,
                'idea' => $idea->load('approvals.department', 'approvals.manager'),
                'message' => 'Idea rejected successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Reject idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return an idea to user for editing
     */
    public function returnToUser(Request $request, $ideaId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'required|string|max:1000|min:3',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $idea = Idea::findOrFail($ideaId);

            // Verify manager has authority for current step
            $this->verifyManagerAuthority($request->user(), $idea);

            // Sanitize comments
            $comments = strip_tags(trim($request->comments));

            $this->workflowService->returnIdea(
                $idea,
                $request->user()->id,
                $comments
            );

            return response()->json([
                'success' => true,
                'idea' => $idea->load('approvals.department', 'approvals.manager'),
                'message' => 'Idea returned to user successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Return idea error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify manager has authority for the current approval step
     */
    private function verifyManagerAuthority($user, $idea)
    {
        if (!$user->isManager()) {
            throw new \Exception('User must be a manager');
        }

        // Get departments managed by this user
        $managedDepartmentIds = $user->managedDepartments()->pluck('departments.id')->toArray();

        // Get the current approval record
        $currentApproval = $idea->approvals()
            ->where('step', $idea->current_approval_step)
            ->where('status', 'pending')
            ->first();

        if (!$currentApproval) {
            throw new \Exception('No pending approval found for this idea');
        }

        if (!in_array($currentApproval->department_id, $managedDepartmentIds)) {
            throw new \Exception('You do not have authority to review this idea at current step');
        }

        return true;
    }

    /**
     * Get all ideas (for managers to view)
     */
    public function allIdeas(Request $request)
    {
        try {
            $ideas = Idea::with('user', 'approvals.department', 'approvals.manager')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'ideas' => $ideas,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get all ideas error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ideas'
            ], 500);
        }
    }
}
