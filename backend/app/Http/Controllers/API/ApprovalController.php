<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\IdeaStatusChanged;
use App\Models\DepartmentManager;
use App\Models\Idea;
use App\Services\IdeaWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

            // Get current department name before approval
            $currentApproval = $idea->approvals()
                ->where('step', $idea->current_approval_step)
                ->with('department')
                ->first();
            $departmentName = $currentApproval->department->name;

            $this->workflowService->approveIdea(
                $idea,
                $request->user()->id,
                $comments
            );

            // Send email notification to user
            try {
                Mail::to($idea->user->email)->send(
                    new IdeaStatusChanged($idea, 'approved', $departmentName, $comments)
                );
            } catch (\Exception $mailError) {
                \Log::error('Failed to send approval email: ' . $mailError->getMessage());
            }

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

            // Get current department name before rejection
            $currentApproval = $idea->approvals()
                ->where('step', $idea->current_approval_step)
                ->with('department')
                ->first();
            $departmentName = $currentApproval->department->name;

            $this->workflowService->rejectIdea(
                $idea,
                $request->user()->id,
                $comments
            );

            // Send email notification to user
            try {
                Mail::to($idea->user->email)->send(
                    new IdeaStatusChanged($idea, 'rejected', $departmentName, $comments)
                );
            } catch (\Exception $mailError) {
                \Log::error('Failed to send rejection email: ' . $mailError->getMessage());
            }

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

            // Get current department name before returning
            $currentApproval = $idea->approvals()
                ->where('step', $idea->current_approval_step)
                ->with('department')
                ->first();
            $departmentName = $currentApproval->department->name;

            $this->workflowService->returnIdea(
                $idea,
                $request->user()->id,
                $comments
            );

            // Send email notification to user
            try {
                Mail::to($idea->user->email)->send(
                    new IdeaStatusChanged($idea, 'returned', $departmentName, $comments)
                );
            } catch (\Exception $mailError) {
                \Log::error('Failed to send return email: ' . $mailError->getMessage());
            }

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

        // Get the current approval record
        $currentApproval = $idea->approvals()
            ->where('step', $idea->current_approval_step)
            ->where('status', 'pending')
            ->first();

        if (!$currentApproval) {
            throw new \Exception('No pending approval found for this idea');
        }

        // Check if manager has 'approver' permission for this department
        $hasApproverPermission = DepartmentManager::where('user_id', $user->id)
            ->where('department_id', $currentApproval->department_id)
            ->where('permission', 'approver')
            ->exists();

        if (!$hasApproverPermission) {
            throw new \Exception('You do not have permission to take action on this idea. You may only have viewer access.');
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
