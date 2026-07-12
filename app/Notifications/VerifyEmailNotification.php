<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Bus\Queueable;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Подтвердите ваш email')
            ->view('mail.verify-email', [
                'url'  => $url,
                'name' => $notifiable->name,
            ]);
    }
}
