<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Send email notification for a specific event
     */
    public function sendNotification(string $eventType, User $recipient, array $data = []): bool
    {
        try {
            // Get the email template
            $template = EmailTemplate::where('event_type', $eventType)
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning("Email template not found for event: {$eventType}");
                return false;
            }

            // Determine language (default to user's language or 'ar')
            $language = $data['language'] ?? 'ar';
            $subject = $language === 'ar' ? $template->subject_ar : $template->subject_en;
            $body = $language === 'ar' ? $template->body_ar : $template->body_en;

            // Replace placeholders in subject and body
            $subject = $this->replacePlaceholders($subject, $data);
            $body = $this->replacePlaceholders($body, $data);

            // Send email
            Mail::raw($body, function ($message) use ($recipient, $subject) {
                $message->to($recipient->email, $recipient->name)
                    ->subject($subject);
            });

            Log::info("Email sent successfully to {$recipient->email} for event: {$eventType}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send email for event {$eventType}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email to multiple recipients
     */
    public function sendBulkNotification(string $eventType, array $recipients, array $data = []): int
    {
        $successCount = 0;

        foreach ($recipients as $recipient) {
            if ($this->sendNotification($eventType, $recipient, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Replace placeholders in text with actual data
     */
    protected function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $text = str_replace($placeholder, $value, $text);
        }

        return $text;
    }

    /**
     * Get available placeholders for a specific event type
     */
    public function getAvailablePlaceholders(string $eventType): array
    {
        $commonPlaceholders = [
            'user_name' => 'Name of the request creator',
            'request_id' => 'Request ID number',
            'request_title' => 'Title of the request',
            'status' => 'Current status of the request',
            'created_at' => 'Request creation date',
            'comments' => 'Additional comments'
        ];

        $eventSpecificPlaceholders = [
            'request.path_assigned' => [
                'workflow_path' => 'Assigned workflow path name',
                'assigned_by' => 'Person who assigned the path',
                'assigned_at' => 'Assignment date'
            ],
            'request.assigned_to_employee' => [
                'employee_name' => 'Employee assigned to request',
                'department' => 'Department name',
                'assigned_by' => 'Person who made the assignment',
                'assigned_at' => 'Assignment date'
            ],
            'request.moved_to_department' => [
                'department' => 'New department name',
                'moved_at' => 'Move date'
            ],
            'request.approved' => [
                'approved_by' => 'Person who approved',
                'approved_at' => 'Approval date'
            ],
            'request.rejected' => [
                'rejected_by' => 'Person who rejected',
                'rejected_at' => 'Rejection date',
                'reason' => 'Rejection reason'
            ],
            'request.need_more_details' => [
                'requested_by' => 'Person requesting more details'
            ],
            'request.completed' => [
                'completed_by' => 'Person who completed',
                'completed_at' => 'Completion date'
            ],
            'request.returned' => [
                'department' => 'Department returned to',
                'returned_by' => 'Person who returned',
                'returned_at' => 'Return date',
                'reason' => 'Return reason'
            ]
        ];

        return array_merge(
            $commonPlaceholders,
            $eventSpecificPlaceholders[$eventType] ?? []
        );
    }
}
