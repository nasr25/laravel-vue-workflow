<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Request as WorkflowRequest;

class RequestStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $request;
    protected $oldStatus;
    protected $newStatus;
    protected $actionBy;
    protected $comment;

    /**
     * Create a new notification instance.
     */
    public function __construct(WorkflowRequest $request, $oldStatus, $newStatus, $actionBy, $comment = null)
    {
        $this->request = $request;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->actionBy = $actionBy;
        $this->comment = $comment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $statusLabels = [
            'draft' => 'Draft',
            'pending' => 'Pending Review',
            'in_review' => 'In Review',
            'need_more_details' => 'Need More Details',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed'
        ];

        $mail = (new MailMessage)
                    ->subject('Request Status Update: ' . $this->request->title)
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('The status of your request has been updated.')
                    ->line('**Request Title:** ' . $this->request->title)
                    ->line('**Previous Status:** ' . ($statusLabels[$this->oldStatus] ?? $this->oldStatus))
                    ->line('**New Status:** ' . ($statusLabels[$this->newStatus] ?? $this->newStatus))
                    ->line('**Updated By:** ' . $this->actionBy->name);

        if ($this->comment) {
            $mail->line('**Comment:** ' . $this->comment);
        }

        if ($this->request->current_department) {
            $mail->line('**Current Department:** ' . $this->request->current_department->name);
        }

        $mail->action('View Request Details', url('/requests/' . $this->request->id))
             ->line('Thank you for using our Workflow Management System!');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'request_title' => $this->request->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'action_by' => $this->actionBy->name,
            'comment' => $this->comment,
            'current_department' => $this->request->current_department ? $this->request->current_department->name : null
        ];
    }
}
