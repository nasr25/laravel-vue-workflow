<?php

namespace App\Console\Commands;

use App\Mail\ManagerReminder;
use App\Models\DepartmentManager;
use App\Models\IdeaApproval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendManagerReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-manager';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails to managers with pending approvals older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for pending approvals requiring reminders...');

        $threshold = Carbon::now()->subHours(24);

        // Find pending approvals that:
        // 1. Arrived more than 24 hours ago
        // 2. Haven't been reviewed yet
        // 3. Haven't received a reminder yet (or last reminder was >24h ago)
        $pendingApprovals = IdeaApproval::with(['idea.user', 'department'])
            ->where('status', 'pending')
            ->whereNotNull('arrived_at')
            ->where('arrived_at', '<=', $threshold)
            ->where(function($query) use ($threshold) {
                $query->whereNull('reminder_sent_at')
                      ->orWhere('reminder_sent_at', '<=', $threshold);
            })
            ->get();

        if ($pendingApprovals->isEmpty()) {
            $this->info('No pending approvals requiring reminders.');
            return 0;
        }

        $remindersSent = 0;

        foreach ($pendingApprovals as $approval) {
            // Get managers with 'approver' permission for this department
            $departmentManagers = DepartmentManager::with('user')
                ->where('department_id', $approval->department_id)
                ->where('permission', 'approver')
                ->get();

            foreach ($departmentManagers as $deptManager) {
                $hoursWaiting = Carbon::parse($approval->arrived_at)->diffInHours(Carbon::now());

                try {
                    Mail::to($deptManager->user->email)->send(
                        new ManagerReminder(
                            $approval->idea,
                            $deptManager->user,
                            $approval->department->name,
                            $hoursWaiting
                        )
                    );

                    $remindersSent++;
                    $this->info("Reminder sent to {$deptManager->user->email} for idea: {$approval->idea->name}");
                } catch (\Exception $e) {
                    $this->error("Failed to send reminder to {$deptManager->user->email}: {$e->getMessage()}");
                }
            }

            // Update reminder_sent_at timestamp
            $approval->update(['reminder_sent_at' => Carbon::now()]);
        }

        $this->info("Total reminders sent: {$remindersSent}");
        return 0;
    }
}
