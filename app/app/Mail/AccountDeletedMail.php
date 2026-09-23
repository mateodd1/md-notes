<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $mailLocale,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailLocale === 'es'
            ? 'Tu cuenta de md-notes se ha eliminado'
            : 'Your md-notes account has been deleted');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-deleted');
    }
}
