<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Vite::prefetch(concurrency: 3);
        Carbon::setLocale('ru');

        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Восстановление пароля')
                ->greeting('Здравствуйте!')
                ->line('Вы получили это письмо, так как мы получили запрос на восстановление пароля для вашего аккаунта.')
                ->action('Сбросить пароль', url(route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false)))
                ->line('Эта ссылка для сброса пароля будет действительна в течение ' . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . ' минут.')
                ->line('Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.');
        });

        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Подтверждение Email')
                ->greeting('Добро пожаловать!')
                ->line('Пожалуйста, нажмите на кнопку ниже, чтобы подтвердить ваш адрес электронной почты.')
                ->action('Подтвердить Email', $url)
                ->line('Если вы не создавали аккаунт, просто проигнорируйте это письмо.');
        });
    }
}
