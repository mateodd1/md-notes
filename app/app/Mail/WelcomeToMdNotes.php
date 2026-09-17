<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeToMdNotes extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user, public readonly string $locale = 'en')
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->locale === 'es' ? 'Bienvenido a md-notes' : 'Welcome to md-notes');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welcome');
    }
}
