<?php

namespace App\Mail;

use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public MagicLink $magicLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve Been Invited to Call Grader',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
            with: [
                'url' => route('login.verify', $this->magicLink->token),
                'expiresAt' => $this->magicLink->expires_at,
                'userName' => $this->user->name,
                'role' => ucfirst(str_replace('_', ' ', $this->user->role)),
            ],
        );
    }
}
