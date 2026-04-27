<?php

namespace App\Console\Commands;

use App\Events\NewNotification;
use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Console\Command;

class SendTestNotification extends Command
{
    protected $signature = 'notify:test';
    protected $description = 'Отправить тестовые уведомления пользователю (интерактивный режим)';

    public function handle(): int
    {
        $input = $this->ask('ID или email пользователя');

        $user = filter_var($input, FILTER_VALIDATE_INT)
            ? User::find((int) $input)
            : User::where('email', $input)->first();

        if (!$user) {
            $this->error("Пользователь «{$input}» не найден.");
            return self::FAILURE;
        }

        $this->line("Пользователь: <info>{$user->name}</info> (id={$user->id})");

        $message = $this->ask('Текст уведомления', 'Push-уведомления работают корректно!');

        $count = (int) $this->ask('Сколько уведомлений отправить?', '1');
        if ($count < 1) $count = 1;

        if (!$this->confirm("Отправить {$count} уведомл. пользователю {$user->name}?", true)) {
            $this->line('Отменено.');
            return self::SUCCESS;
        }

        for ($i = 0; $i < $count; $i++) {
            $num  = $i + 1;
            $text = $count > 1 ? "{$message} (#{$num})" : $message;
            $user->notify(new TestNotification($text));
            event(new NewNotification('private', $user->id));
        }

        $this->info("Отправлено {$count} уведомл. → {$user->name} (id={$user->id}).");
        return self::SUCCESS;
    }
}
