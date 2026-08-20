<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdolApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, $this->user->name)],
            subject: 'Поздравляем! Вы стали Айдолом',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.idol-approved',
            with: [
                'name' => $this->user->name,
                'profileUrl' => url('/users/'.$this->user->id),
            ],
        );
    }
}
