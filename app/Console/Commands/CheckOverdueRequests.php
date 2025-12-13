<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Request;
use App\Models\Department;
use App\Services\EmailNotificationService;
use Carbon\Carbon;

class CheckOverdueRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-overdue-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue requests and send reminder emails to managers';

    protected $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue requests...');

        $overdueCount = 0;

        // 1. Check pending requests (Department A needs to assign path)
        $overdueCount += $this->checkPendingRequests();

        // 2. Check in_review requests (Department manager needs to assign employee)
        $overdueCount += $this->checkManagerReviewRequests();

        // 3. Check in_progress and missing_requirement requests (Employee needs to complete work)
        $overdueCount += $this->checkEmployeeWorkRequests();

        // 4. Check final validation requests (Department A final review)
        $overdueCount += $this->checkFinalValidationRequests();

        $this->info("Total overdue requests processed: {$overdueCount}");

        return 0;
    }

    /**
     * Check pending requests waiting for Department A to assign path
     */
    private function checkPendingRequests()
    {
        $days = config('sla.dept_a_review_days', 3);
        $cutoffDate = Carbon::now()->subDays($days);

        $requests = Request::where('status', 'pending')
            ->whereNotNull('current_stage_started_at')
            ->where('current_stage_started_at', '<=', $cutoffDate)
            ->where(function ($query) use ($cutoffDate) {
                $query->whereNull('sla_reminder_sent_at')
                    ->orWhere('sla_reminder_sent_at', '<=', $cutoffDate);
            })
            ->get();

        $count = 0;
        foreach ($requests as $request) {
            $this->sendReminderToDepartmentA($request, 'assign_path');
            $count++;
        }

        $this->info("Pending requests (Dept A review): {$count}");
        return $count;
    }

    /**
     * Check in_review requests waiting for department manager to assign employee
     */
    private function checkManagerReviewRequests()
    {
        $days = config('sla.dept_manager_review_days', 5);
        $cutoffDate = Carbon::now()->subDays($days);

        $requests = Request::where('status', 'in_review')
            ->whereNotNull('workflow_path_id')
            ->whereNull('current_user_id')
            ->whereNotNull('current_stage_started_at')
            ->where('current_stage_started_at', '<=', $cutoffDate)
            ->where(function ($query) use ($cutoffDate) {
                $query->whereNull('sla_reminder_sent_at')
                    ->orWhere('sla_reminder_sent_at', '<=', $cutoffDate);
            })
            ->get();

        $count = 0;
        foreach ($requests as $request) {
            $this->sendReminderToDepartmentManager($request, 'assign_employee');
            $count++;
        }

        $this->info("In review requests (Manager assign): {$count}");
        return $count;
    }

    /**
     * Check in_progress and missing_requirement requests waiting for employee to complete
     */
    private function checkEmployeeWorkRequests()
    {
        $days = config('sla.employee_work_days', 7);
        $cutoffDate = Carbon::now()->subDays($days);

        $requests = Request::whereIn('status', ['in_progress', 'missing_requirement'])
            ->whereNotNull('current_user_id')
            ->whereNotNull('current_stage_started_at')
            ->where('current_stage_started_at', '<=', $cutoffDate)
            ->where(function ($query) use ($cutoffDate) {
                $query->whereNull('sla_reminder_sent_at')
                    ->orWhere('sla_reminder_sent_at', '<=', $cutoffDate);
            })
            ->get();

        $count = 0;
        foreach ($requests as $request) {
            $this->sendReminderToDepartmentManager($request, 'employee_overdue');
            $count++;
        }

        $this->info("Employee work requests: {$count}");
        return $count;
    }

    /**
     * Check requests waiting for Department A final validation
     */
    private function checkFinalValidationRequests()
    {
        $days = config('sla.final_validation_days', 2);
        $cutoffDate = Carbon::now()->subDays($days);

        // Get Department A
        $deptA = Department::where('is_department_a', true)->first();
        if (!$deptA) {
            return 0;
        }

        $requests = Request::where('status', 'in_review')
            ->whereNotNull('workflow_path_id')
            ->where('current_department_id', $deptA->id)
            ->whereNotNull('current_stage_started_at')
            ->where('current_stage_started_at', '<=', $cutoffDate)
            ->where(function ($query) use ($cutoffDate) {
                $query->whereNull('sla_reminder_sent_at')
                    ->orWhere('sla_reminder_sent_at', '<=', $cutoffDate);
            })
            ->get();

        $count = 0;
        foreach ($requests as $request) {
            $this->sendReminderToDepartmentA($request, 'final_validation');
            $count++;
        }

        $this->info("Final validation requests: {$count}");
        return $count;
    }

    /**
     * Send reminder to Department A managers
     */
    private function sendReminderToDepartmentA(Request $request, string $action)
    {
        $deptA = Department::where('is_department_a', true)->first();
        if (!$deptA) {
            return;
        }

        $managers = $deptA->managers;
        if ($managers->isEmpty()) {
            return;
        }

        // Determine event type based on action
        $eventType = $action === 'assign_path' ? 'sla.dept_a_assign_path' : 'sla.final_validation_overdue';

        // Get SLA days
        $slaDays = $action === 'assign_path'
            ? config('sla.dept_a_review_days', 3)
            : config('sla.final_validation_days', 2);

        // Calculate days waiting
        $daysWaiting = $request->current_stage_started_at
            ? Carbon::now()->diffInDays($request->current_stage_started_at)
            : 0;

        // Prepare email data
        $emailData = [
            'request_id' => $request->id,
            'request_title' => $request->title,
            'user_name' => $request->user->name ?? 'Unknown',
            'submitted_at' => $request->current_stage_started_at?->format('Y-m-d H:i') ?? 'N/A',
            'returned_at' => $request->current_stage_started_at?->format('Y-m-d H:i') ?? 'N/A',
            'days_waiting' => $daysWaiting,
            'sla_days' => $slaDays,
            'language' => 'ar' // Default to Arabic, can be made configurable per user
        ];

        // Send email to each manager
        foreach ($managers as $manager) {
            $emailData['manager_name'] = $manager->name;
            $this->emailService->sendNotification($eventType, $manager, $emailData);
        }

        // Update SLA reminder timestamp
        $request->update(['sla_reminder_sent_at' => Carbon::now()]);

        $this->info("Sent reminder for request #{$request->id} to Department A managers");
    }

    /**
     * Send reminder to current department managers
     */
    private function sendReminderToDepartmentManager(Request $request, string $action)
    {
        if (!$request->current_department_id) {
            return;
        }

        $department = Department::find($request->current_department_id);
        if (!$department) {
            return;
        }

        $managers = $department->managers;
        if ($managers->isEmpty()) {
            return;
        }

        // Determine event type based on action
        $eventType = $action === 'assign_employee' ? 'sla.manager_assign_employee' : 'sla.employee_work_overdue';

        // Get SLA days
        $slaDays = $action === 'assign_employee'
            ? config('sla.dept_manager_review_days', 5)
            : config('sla.employee_work_days', 7);

        // Calculate days waiting
        $daysWaiting = $request->current_stage_started_at
            ? Carbon::now()->diffInDays($request->current_stage_started_at)
            : 0;

        // Prepare email data
        $emailData = [
            'request_id' => $request->id,
            'request_title' => $request->title,
            'user_name' => $request->user->name ?? 'Unknown',
            'department' => $department->name,
            'days_waiting' => $daysWaiting,
            'sla_days' => $slaDays,
            'language' => 'ar' // Default to Arabic, can be made configurable per user
        ];

        // Add employee-specific data if applicable
        if ($action === 'employee_overdue' && $request->currentAssignee) {
            $emailData['employee_name'] = $request->currentAssignee->name;
            $emailData['assigned_at'] = $request->current_stage_started_at?->format('Y-m-d H:i') ?? 'N/A';
        }

        // Send email to each manager
        foreach ($managers as $manager) {
            $emailData['manager_name'] = $manager->name;
            $this->emailService->sendNotification($eventType, $manager, $emailData);
        }

        // Update SLA reminder timestamp
        $request->update(['sla_reminder_sent_at' => Carbon::now()]);

        $this->info("Sent reminder for request #{$request->id} to {$department->name} managers");
    }
}
