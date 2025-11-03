<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\IdeaApproval;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdeaWorkflowService
{
    /**
     * Submit an idea and start the approval workflow
     */
    public function submitIdea(Idea $idea)
    {
        DB::beginTransaction();

        try {
            // Get active departments ordered by approval_order
            $departments = Department::where('is_active', true)
                ->orderBy('approval_order')
                ->get();

            if ($departments->isEmpty()) {
                throw new \Exception('No active departments available. Please contact administrator.');
            }

            // Get the first active department's approval order
            $firstDepartment = $departments->first();

            // Update idea status to pending
            $idea->update([
                'status' => 'pending',
                'current_approval_step' => $firstDepartment->approval_order,
            ]);

            // Create approval records only for active departments
            foreach ($departments as $department) {
                IdeaApproval::create([
                    'idea_id' => $idea->id,
                    'department_id' => $department->id,
                    'step' => $department->approval_order,
                    'status' => 'pending',
                    // Set arrived_at for the first department
                    'arrived_at' => $department->id === $firstDepartment->id ? now() : null,
                ]);
            }

            DB::commit();

            Log::info('Idea submitted successfully', [
                'idea_id' => $idea->id,
                'user_id' => $idea->user_id,
                'first_department' => $firstDepartment->name,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit idea', [
                'idea_id' => $idea->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Approve idea at current step
     */
    public function approveIdea(Idea $idea, $managerId, $comments = null)
    {
        DB::beginTransaction();

        try {
            $currentStep = $idea->current_approval_step;

            // Get the approval record for current step
            $approval = IdeaApproval::where('idea_id', $idea->id)
                ->where('step', $currentStep)
                ->where('status', 'pending')
                ->first();

            if (!$approval) {
                throw new \Exception('No pending approval found for this step');
            }

            // Update approval record
            $approval->update([
                'status' => 'approved',
                'manager_id' => $managerId,
                'comments' => $comments,
                'reviewed_at' => now(),
            ]);

            // Get all active departments to determine workflow
            $activeDepartments = Department::where('is_active', true)
                ->orderBy('approval_order')
                ->get();

            // Find next active department after current step
            $nextDepartment = $activeDepartments->first(function ($dept) use ($currentStep) {
                return $dept->approval_order > $currentStep;
            });

            if ($nextDepartment) {
                // Move to next active department
                $idea->update([
                    'current_approval_step' => $nextDepartment->approval_order,
                ]);

                // Set arrived_at for the next department's approval
                IdeaApproval::where('idea_id', $idea->id)
                    ->where('department_id', $nextDepartment->id)
                    ->update(['arrived_at' => now()]);

                Log::info('Idea moved to next department', [
                    'idea_id' => $idea->id,
                    'next_department' => $nextDepartment->name,
                    'next_step' => $nextDepartment->approval_order,
                ]);
            } else {
                // All active departments have approved - mark as fully approved
                // Keep current_approval_step at the last department (don't set to null)
                $idea->update([
                    'status' => 'approved',
                    // Keep current_approval_step at the last approved step
                ]);

                Log::info('Idea fully approved', [
                    'idea_id' => $idea->id,
                    'final_step' => $currentStep,
                ]);
            }

            DB::commit();

            Log::info('Idea approved', [
                'idea_id' => $idea->id,
                'step' => $currentStep,
                'manager_id' => $managerId,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve idea', [
                'idea_id' => $idea->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject idea
     */
    public function rejectIdea(Idea $idea, $managerId, $comments = null)
    {
        DB::beginTransaction();

        try {
            $currentStep = $idea->current_approval_step;

            // Get the approval record for current step
            $approval = IdeaApproval::where('idea_id', $idea->id)
                ->where('step', $currentStep)
                ->where('status', 'pending')
                ->first();

            if (!$approval) {
                throw new \Exception('No pending approval found for this step');
            }

            // Update approval record
            $approval->update([
                'status' => 'rejected',
                'manager_id' => $managerId,
                'comments' => $comments,
                'reviewed_at' => now(),
            ]);

            // Update idea status to rejected
            $idea->update([
                'status' => 'rejected',
            ]);

            DB::commit();

            Log::info('Idea rejected', [
                'idea_id' => $idea->id,
                'step' => $currentStep,
                'manager_id' => $managerId,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject idea', [
                'idea_id' => $idea->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Return idea to user for editing
     */
    public function returnIdea(Idea $idea, $managerId, $comments)
    {
        DB::beginTransaction();

        try {
            $currentStep = $idea->current_approval_step;

            // Get the approval record for current step
            $approval = IdeaApproval::where('idea_id', $idea->id)
                ->where('step', $currentStep)
                ->where('status', 'pending')
                ->first();

            if (!$approval) {
                throw new \Exception('No pending approval found for this step');
            }

            // Update approval record
            $approval->update([
                'status' => 'returned',
                'manager_id' => $managerId,
                'comments' => $comments,
                'reviewed_at' => now(),
            ]);

            // Update idea status to returned
            $idea->update([
                'status' => 'returned',
                'current_approval_step' => 0, // Reset to draft
            ]);

            // Reset all approval records to allow resubmission
            IdeaApproval::where('idea_id', $idea->id)->delete();

            DB::commit();

            Log::info('Idea returned to user', [
                'idea_id' => $idea->id,
                'step' => $currentStep,
                'manager_id' => $managerId,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to return idea', [
                'idea_id' => $idea->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get ideas pending approval for a specific manager
     */
    public function getPendingIdeasForManager($managerId)
    {
        $user = \App\Models\User::find($managerId);

        if (!$user || !$user->isManager()) {
            throw new \Exception('User is not a manager');
        }

        // Get departments managed by this user
        $departmentIds = $user->managedDepartments()->pluck('departments.id');

        // Get ideas that are at current approval step matching manager's departments
        $ideas = Idea::whereIn('status', ['pending'])
            ->where(function ($query) use ($departmentIds) {
                // Only get ideas where the current step matches a department this manager manages
                $query->whereHas('approvals', function ($approvalQuery) use ($departmentIds) {
                    $approvalQuery->where('status', 'pending')
                        ->whereIn('department_id', $departmentIds)
                        ->whereRaw('step = (SELECT current_approval_step FROM ideas WHERE ideas.id = idea_approvals.idea_id)');
                });
            })
            ->with(['user', 'approvals.department', 'approvals.manager'])
            ->get();

        return $ideas;
    }
}
