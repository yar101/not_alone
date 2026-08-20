<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdolRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, $this->user->name)],
            subject: 'Заявка на статус Айдола отклонена',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.idol-rejected',
            with: [
                'name' => $this->user->name,
                'reason' => $this->reason,
                'applyUrl' => url('/idol/apply'),
            ],
        );
    }
}
