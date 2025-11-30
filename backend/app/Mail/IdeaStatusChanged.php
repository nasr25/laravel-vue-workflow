<?php

namespace App\Mail;

use App\Models\Idea;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdeaStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public $idea;
    public $action; // 'approved', 'rejected', 'returned'
    public $comments;
    public $departmentName;

    /**
     * Create a new message instance.
     */
    public function __construct(Idea $idea, string $action, string $departmentName, ?string $comments = null)
    {
        $this->idea = $idea;
        $this->action = $action;
        $this->comments = $comments;
        $this->departmentName = $departmentName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $actionText = ucfirst($this->action);
        return new Envelope(
            subject: "Your Idea '{$this->idea->name}' was {$actionText}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.idea-status-changed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
