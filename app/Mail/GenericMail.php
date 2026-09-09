<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GenericMail extends Mailable
{
    use Queueable;

    public function __construct(
        string $subject,
        public string $body,
        public ?string $fromName = null,
    ) {
        $this->subject = $subject;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'body' => $this->body,
                'appName' => config('app.name', 'TaziJobs'),
                'fromName' => $this->fromName ?? config('mail.from.name', config('app.name', 'TaziJobs')),
            ],
        );
    }
}
