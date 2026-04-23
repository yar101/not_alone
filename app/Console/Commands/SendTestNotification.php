<?php

namespace App\Console\Commands;

use App\Events\NewNotification;
use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Console\Command;

class SendTestNotification extends Command
{
    protected $signature = 'notify:test {user : ID или email пользователя} {--message= : Текст уведомления}';
    protected $description = 'Отправить тестовое уведомление пользователю';

    public function handle(): int
    {
        $input = $this->argument('user');

        $user = filter_var($input, FILTER_VALIDATE_INT)
            ? User::find((int) $input)
            : User::where('email', $input)->first();

        if (!$user) {
            $this->error("Пользователь «{$input}» не найден.");
            return self::FAILURE;
        }

        $message = $this->option('message') ?? 'Тестовое уведомление';

        $user->notify(new TestNotification($message));
        event(new NewNotification('private', $user->id));

        $this->info("Уведомление отправлено пользователю {$user->name} (id={$user->id}).");
        return self::SUCCESS;
    }
}
