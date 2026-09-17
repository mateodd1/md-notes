<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $purpose,
        public readonly string $code,
        public readonly string $locale,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->locale === 'es'
            ? 'Código de seguridad de md-notes'
            : 'Your md-notes security code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.profile-code');
    }
}
