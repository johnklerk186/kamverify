<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic branded admin-activity email. Only plain scalars are carried —
 * no models — so a queued send never re-reads stale state and nothing
 * sensitive (keys, OTPs, message bodies) can leak through serialization.
 */
class AdminActivityMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $heading,
        public array $fields,
        public ?string $note = null,
    ) {
        // An email job failing must never surface into the request/worker
        // as an unhandled error storm — a couple of retries is enough.
        $this->tries = 2;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-activity');
    }
}
