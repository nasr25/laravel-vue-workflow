<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\IdeaApproval;
use App\Models\Department;
use App\Models\FormType;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepApprover;
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
     * Return idea to a specific department
     */
    public function returnToDepartment(Idea $idea, $managerId, $targetDepartmentId, $comments)
    {
        DB::beginTransaction();

        try {
            $currentStep = $idea->current_approval_step;

            // Get the approval record for current step
            $currentApproval = IdeaApproval::where('idea_id', $idea->id)
                ->where('step', $currentStep)
                ->where('status', 'pending')
                ->first();

            if (!$currentApproval) {
                throw new \Exception('No pending approval found for this step');
            }

            // Get the target department
            $targetDepartment = Department::findOrFail($targetDepartmentId);

            // Verify target department is before current step (can only return backwards)
            if ($targetDepartment->approval_order >= $currentStep) {
                throw new \Exception('Can only return to previous departments');
            }

            // Reset all approvals from target department onwards (including current step)
            // This ensures when the target department approves again, all subsequent steps are pending
            IdeaApproval::where('idea_id', $idea->id)
                ->where('step', '>=', $targetDepartment->approval_order)
                ->update([
                    'status' => 'pending',
                    'manager_id' => null,
                    'comments' => null,
                    'reviewed_at' => null,
                    'arrived_at' => null,
                ]);

            // Update idea to move to target department
            $idea->update([
                'status' => 'pending',
                'current_approval_step' => $targetDepartment->approval_order,
            ]);

            // Set arrived_at for the target department
            IdeaApproval::where('idea_id', $idea->id)
                ->where('department_id', $targetDepartmentId)
                ->update(['arrived_at' => now()]);

            DB::commit();

            Log::info('Idea returned to department', [
                'idea_id' => $idea->id,
                'from_step' => $currentStep,
                'to_department' => $targetDepartment->name,
                'to_step' => $targetDepartment->approval_order,
                'manager_id' => $managerId,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to return idea to department', [
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

    // ========================================
    // DYNAMIC WORKFLOW METHODS (NEW)
    // ========================================

    /**
     * Submit an idea with dynamic workflow based on form type
     */
    public function submitIdeaWithWorkflow(Idea $idea)
    {
        DB::beginTransaction();

        try {
            // Check if idea has workflow template
            if (!$idea->workflow_template_id) {
                throw new \Exception('Idea must have a workflow template assigned');
            }

            // Load workflow template with steps
            $workflowTemplate = WorkflowTemplate::with(['steps' => function ($query) {
                $query->orderBy('step_order');
            }])->findOrFail($idea->workflow_template_id);

            if ($workflowTemplate->steps->isEmpty()) {
                throw new \Exception('Workflow template has no steps defined');
            }

            // Get first step
            $firstStep = $workflowTemplate->steps->first();

            // Update idea status to pending
            $idea->update([
                'status' => 'pending',
                'current_approval_step' => $firstStep->step_order,
            ]);

            // Create approval records for each workflow step
            foreach ($workflowTemplate->steps as $step) {
                IdeaApproval::create([
                    'idea_id' => $idea->id,
                    'department_id' => $step->department_id,
                    'workflow_step_id' => $step->id,
                    'step' => $step->step_order,
                    'status' => 'pending',
                    'approver_type' => $step->approver_type,
                    'approvals_received' => 0,
                    'approvals_required' => $step->required_approvals_count,
                    'arrived_at' => $step->id === $firstStep->id ? now() : null,
                ]);
            }

            DB::commit();

            Log::info('Idea submitted with dynamic workflow', [
                'idea_id' => $idea->id,
                'workflow_template_id' => $workflowTemplate->id,
                'total_steps' => $workflowTemplate->steps->count(),
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit idea with workflow', [
                'idea_id' => $idea->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process approval by employee or manager
     */
    public function processApproval(Idea $idea, $approverId, $approverType, $comments = null)
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

            // Verify approver type matches
            if ($approval->approver_type !== $approverType && $approval->approver_type !== 'either') {
                throw new \Exception("This step requires approval from: {$approval->approver_type}");
            }

            // Check if this user has already approved this step (prevent duplicate approvals)
            $existingApproval = IdeaApproval::where('idea_id', $idea->id)
                ->where('step', $currentStep)
                ->where('approver_id', $approverId)
                ->exists();

            if ($existingApproval && $approval->approvals_received > 0) {
                throw new \Exception('You have already approved this step');
            }

            // Increment approval count
            $approval->increment('approvals_received');
            $approval->update([
                'approver_id' => $approverId,
                'comments' => $comments,
                'reviewed_at' => now(),
            ]);

            // Check if step is now complete
            if ($approval->approvals_received >= $approval->approvals_required) {
                $approval->update(['status' => 'approved']);
                $this->moveToNextStep($idea, $approval);
            }

            DB::commit();

            Log::info('Approval processed', [
                'idea_id' => $idea->id,
                'approver_id' => $approverId,
                'approver_type' => $approverType,
                'step' => $currentStep,
                'approvals' => "{$approval->approvals_received}/{$approval->approvals_required}",
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process approval', [
                'idea_id' => $idea->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Move idea to next workflow step
     */
    protected function moveToNextStep(Idea $idea, IdeaApproval $currentApproval)
    {
        // Load workflow template
        $workflowTemplate = WorkflowTemplate::with(['steps' => function ($query) {
            $query->orderBy('step_order');
        }])->find($idea->workflow_template_id);

        if (!$workflowTemplate) {
            // Old workflow system - use existing logic
            return;
        }

        // Find next step
        $nextStep = $workflowTemplate->steps->first(function ($step) use ($currentApproval) {
            return $step->step_order > $currentApproval->step;
        });

        if ($nextStep) {
            // Move to next step
            $idea->update([
                'current_approval_step' => $nextStep->step_order,
            ]);

            // Set arrived_at for next step
            IdeaApproval::where('idea_id', $idea->id)
                ->where('step', $nextStep->step_order)
                ->update(['arrived_at' => now()]);

            Log::info('Idea moved to next step', [
                'idea_id' => $idea->id,
                'next_step' => $nextStep->step_order,
                'next_step_name' => $nextStep->step_name,
            ]);
        } else {
            // All steps complete - mark as fully approved
            $idea->update(['status' => 'approved']);

            Log::info('Idea workflow completed', [
                'idea_id' => $idea->id,
            ]);
        }
    }

    /**
     * Get ideas pending approval for a specific employee
     */
    public function getPendingIdeasForEmployee($employeeId)
    {
        $user = \App\Models\User::find($employeeId);

        if (!$user || !$user->isEmployee()) {
            throw new \Exception('User is not an employee');
        }

        // Get workflow steps where this employee is assigned
        $workflowStepIds = WorkflowStepApprover::where('user_id', $employeeId)
            ->pluck('workflow_step_id');

        // Get ideas at current approval step matching employee's assigned workflow steps
        $ideas = Idea::where('status', 'pending')
            ->whereHas('approvals', function ($query) use ($workflowStepIds) {
                $query->where('status', 'pending')
                    ->whereIn('workflow_step_id', $workflowStepIds)
                    ->whereRaw('step = (SELECT current_approval_step FROM ideas WHERE ideas.id = idea_approvals.idea_id)');
            })
            ->with(['user', 'formType', 'workflowTemplate', 'approvals.department', 'approvals.workflowStep'])
            ->get();

        return $ideas;
    }

    /**
     * Check if user can approve a specific idea
     */
    public function canUserApprove(Idea $idea, $userId, $userType)
    {
        $currentStep = $idea->current_approval_step;

        $approval = IdeaApproval::where('idea_id', $idea->id)
            ->where('step', $currentStep)
            ->where('status', 'pending')
            ->first();

        if (!$approval) {
            return false;
        }

        // Check if user type matches required approver type
        if ($approval->approver_type !== $userType && $approval->approver_type !== 'either') {
            return false;
        }

        // For employees, check if they're assigned to this workflow step
        if ($userType === 'employee') {
            $isAssigned = WorkflowStepApprover::where('workflow_step_id', $approval->workflow_step_id)
                ->where('user_id', $userId)
                ->exists();

            return $isAssigned;
        }

        // For managers, check if they manage the department
        if ($userType === 'manager') {
            $user = \App\Models\User::find($userId);
            $departmentIds = $user->managedDepartments()->pluck('departments.id');

            return $departmentIds->contains($approval->department_id);
        }

        return false;
    }

    /**
     * Reject idea (works for both old and new workflow)
     */
    public function rejectIdeaByApprover(Idea $idea, $approverId, $approverType, $comments = null)
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
                'approver_id' => $approverId,
                'approver_type' => $approverType,
                'comments' => $comments,
                'reviewed_at' => now(),
            ]);

            // Update idea status to rejected
            $idea->update([
                'status' => 'rejected',
            ]);

            DB::commit();

            Log::info('Idea rejected by approver', [
                'idea_id' => $idea->id,
                'approver_id' => $approverId,
                'approver_type' => $approverType,
                'step' => $currentStep,
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
}
