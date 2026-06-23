<?php

namespace App\Notifications;

use App\Models\UserStrike;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserStrikeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $strike;

    /**
     * Create a new notification instance.
     */
    public function __construct(UserStrike $strike)
    {
        $this->strike = $strike;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $activeStrikes = $notifiable->activeStrikesCount();

        $mail = (new MailMessage)
            ->error()
            ->subject('Уведомление от модерации платформы')
            ->greeting('Уведомление о нарушении')
            ->line('Вы получили это письмо, так как администрация зафиксировала нарушение правил платформы.')
            ->line('Количество ваших активных страйков: ' . $activeStrikes . ' из 3.');

        if ($this->strike->rating_deducted < 0) {
            $mail->line('В связи с нарушением ваш рейтинг был понижен на ' . abs($this->strike->rating_deducted) . '.');
        }

        if (!empty($this->strike->admin_note)) {
            $mail->line('**Примечание от администрации:**');
            $mail->line('"' . $this->strike->admin_note . '"');
        }

        if ($activeStrikes >= 3) {
            $mail->line('Так как вы достигли лимита страйков (3), ваш аккаунт был заблокирован.');
        } else {
            $mail->line('Пожалуйста, будьте внимательны. Страйки автоматически сгорают через 6 месяцев. Если вы получите 3 активных страйка, ваш аккаунт будет заблокирован.');
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'strike_id' => $this->strike->id,
            'rating_deducted' => $this->strike->rating_deducted,
            'admin_note' => $this->strike->admin_note,
            'active_strikes_total' => $notifiable->activeStrikesCount(),
        ];
    }
}
