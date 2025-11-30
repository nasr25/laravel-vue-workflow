<?php

namespace App\Mail;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $idea;
    public $manager;
    public $departmentName;
    public $hoursWaiting;

    /**
     * Create a new message instance.
     */
    public function __construct(Idea $idea, User $manager, string $departmentName, int $hoursWaiting)
    {
        $this->idea = $idea;
        $this->manager = $manager;
        $this->departmentName = $departmentName;
        $this->hoursWaiting = $hoursWaiting;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: Idea '{$this->idea->name}' Awaiting Your Review",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.manager-reminder',
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
