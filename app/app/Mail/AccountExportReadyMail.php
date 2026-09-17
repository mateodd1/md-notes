<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $downloadUrl,
        public readonly string $mailLocale,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailLocale === 'es'
            ? 'Tu descarga de datos de md-notes'
            : 'Your md-notes data download');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-export');
    }
}
