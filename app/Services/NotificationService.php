<?php

namespace App\Services;

use App\Models\User;
use App\Models\Request;
use App\Models\UserSetting;
use App\Models\InAppNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notification type constants
     */
    const TYPE_REQUEST_CREATED = 'request_created';
    const TYPE_REQUEST_STATUS_CHANGED = 'request_status_changed';
    const TYPE_REQUEST_ASSIGNED = 'request_assigned';
    const TYPE_REQUEST_APPROVED = 'request_approved';
    const TYPE_REQUEST_REJECTED = 'request_rejected';
    const TYPE_REQUEST_COMPLETED = 'request_completed';
    const TYPE_SLA_REMINDER = 'sla_reminder';

    /**
     * Send notification to a user
     *
     * @param User $user The user to notify
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param Request|null $request Related request
     * @param array $data Additional data
     * @return void
     */
    public function notify(User $user, string $type, string $title, string $message, ?Request $request = null, array $data = []): void
    {
        // Get user's notification settings
        $settings = $this->getUserSettings($user);

        // Send email if enabled
        if ($this->shouldSendEmail($settings, $type)) {
            $this->sendEmail($user, $type, $title, $message, $request, $data);
        }

        // Create in-app notification if enabled
        if ($this->shouldSendInAppNotification($settings, $type)) {
            $this->createInAppNotification($user, $type, $title, $message, $request, $data);
        }
    }

    /**
     * Send notification to multiple users
     *
     * @param array $users Array of User objects
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param Request|null $request Related request
     * @param array $data Additional data
     * @return void
     */
    public function notifyMultiple(array $users, string $type, string $title, string $message, ?Request $request = null, array $data = []): void
    {
        foreach ($users as $user) {
            if ($user instanceof User) {
                $this->notify($user, $type, $title, $message, $request, $data);
            }
        }
    }

    /**
     * Get user's notification settings
     *
     * @param User $user
     * @return array
     */
    private function getUserSettings(User $user): array
    {
        $userSetting = UserSetting::where('user_id', $user->id)->first();

        if (!$userSetting) {
            // Return default settings (all enabled)
            return [
                'email' => [
                    'request_created' => true,
                    'request_status_changed' => true,
                    'request_assigned' => true,
                    'request_approved' => true,
                    'request_rejected' => true,
                    'request_completed' => true,
                    'sla_reminder' => true,
                ],
                'notification' => [
                    'request_created' => true,
                    'request_status_changed' => true,
                    'request_assigned' => true,
                    'request_approved' => true,
                    'request_rejected' => true,
                    'request_completed' => true,
                    'sla_reminder' => true,
                ],
            ];
        }

        return json_decode($userSetting->settings, true);
    }

    /**
     * Check if email should be sent
     *
     * @param array $settings
     * @param string $type
     * @return bool
     */
    private function shouldSendEmail(array $settings, string $type): bool
    {
        return $settings['email'][$type] ?? true;
    }

    /**
     * Check if in-app notification should be created
     *
     * @param array $settings
     * @param string $type
     * @return bool
     */
    private function shouldSendInAppNotification(array $settings, string $type): bool
    {
        return $settings['notification'][$type] ?? true;
    }

    /**
     * Send email notification
     *
     * @param User $user
     * @param string $type
     * @param string $title
     * @param string $message
     * @param Request|null $request
     * @param array $data
     * @return void
     */
    private function sendEmail(User $user, string $type, string $title, string $message, ?Request $request, array $data): void
    {
        try {
            Mail::send('emails.notification', [
                'user' => $user,
                'title' => $title,
                'message' => $message,
                'request' => $request,
                'data' => $data,
                'type' => $type,
            ], function ($mail) use ($user, $title) {
                $mail->to($user->email)
                    ->subject($title);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create in-app notification
     *
     * @param User $user
     * @param string $type
     * @param string $title
     * @param string $message
     * @param Request|null $request
     * @param array $data
     * @return void
     */
    private function createInAppNotification(User $user, string $type, string $title, string $message, ?Request $request, array $data): void
    {
        try {
            InAppNotification::create([
                'user_id' => $user->id,
                'request_id' => $request?->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create in-app notification', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify all users associated with a request
     *
     * @param Request $request
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @return void
     */
    public function notifyRequestStakeholders(Request $request, string $type, string $title, string $message, array $data = []): void
    {
        $users = [];

        // 1. Notify the request creator
        if ($request->user) {
            $users[] = $request->user;
        }

        // 2. Notify Department A managers (who can assign paths)
        $departmentAManagers = User::whereHas('departments', function ($query) {
            $query->where('departments.id', 1) // Department A is typically id 1
                  ->where('department_user.role', 'manager');
        })->get();
        $users = array_merge($users, $departmentAManagers->all());

        // 3. Notify assigned employee (if any)
        if ($request->assigned_employee_id) {
            $assignedEmployee = User::find($request->assigned_employee_id);
            if ($assignedEmployee) {
                $users[] = $assignedEmployee;
            }
        }

        // 4. Notify department managers in the current workflow path
        if ($request->current_department_id) {
            $departmentManagers = User::whereHas('departments', function ($query) use ($request) {
                $query->where('departments.id', $request->current_department_id)
                      ->where('department_user.role', 'manager');
            })->get();
            $users = array_merge($users, $departmentManagers->all());
        }

        // 5. Notify all admins
        $admins = User::where('role', 'admin')->get();
        $users = array_merge($users, $admins->all());

        // Remove duplicates
        $uniqueUsers = collect($users)->unique('id')->all();

        // Send notifications
        $this->notifyMultiple($uniqueUsers, $type, $title, $message, $request, $data);
    }
}
