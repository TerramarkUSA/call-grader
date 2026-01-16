<?php

namespace App\Mail;

use App\Models\MagicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MagicLink $magicLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Login Link - Call Grader',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
            with: [
                'url' => route('login.verify', $this->magicLink->token),
                'expiresAt' => $this->magicLink->expires_at,
                'userName' => $this->magicLink->user->name,
            ],
        );
    }
}
