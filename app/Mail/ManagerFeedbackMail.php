<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerFeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $feedbackMessage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->user->email, $this->user->name),
            replyTo: [new Address($this->user->email, $this->user->name)],
            subject: "Call Grader Feedback from {$this->user->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manager-feedback',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'userRole' => $this->user->role,
                'feedbackMessage' => $this->feedbackMessage,
                'sentAt' => now()->timezone('America/New_York')->format('l, F j, Y \a\t g:i A T'),
            ],
        );
    }
}
