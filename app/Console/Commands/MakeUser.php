<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\IdolApplication;
use App\Models\User;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeUser extends Command
{
    protected $signature   = 'user:make';
    protected $description = 'Создать тестового пользователя интерактивно';

    public function handle(): int
    {
        $type = $this->choice(
            'Кого создать?',
            ['Обычный пользователь', 'Айдол', 'Админ'],
            0
        );

        match ($type) {
            'Обычный пользователь' => $this->makeUser(isIdol: false),
            'Айдол'               => $this->makeUser(isIdol: true),
            'Админ'               => $this->makeAdmin(),
        };

        return self::SUCCESS;
    }

    private function makeUser(bool $isIdol): void
    {
        $faker  = Factory::create('ru_RU');
        $gender = ['male', 'female'][rand(0, 1)];
        $now    = now();

        $name  = $gender === 'male' ? $faker->firstNameMale() . ' ' . $faker->lastNameMale()
                                    : $faker->firstNameFemale() . ' ' . $faker->lastNameFemale();
        $email = 'user_' . uniqid() . '@example.com';
        $birth = $faker->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d');

        $user = User::create([
            'name'                => $name,
            'email'               => $email,
            'password'            => Hash::make('123123'),
            'email_verified_at'   => $now,
            'gender'              => $gender,
            'birth_date'          => $birth,
            'is_idol'             => $isIdol,
            'idol_rating'         => $isIdol ? 20 : null,
            'idol_quiz_passed_at' => $isIdol ? $now : null,
        ]);

        if ($isIdol) {
            IdolApplication::create([
                'user_id'         => $user->id,
                'face_photo_path' => 'generated',
                'status'          => 'approved',
                'reviewed_at'     => $now,
            ]);
        }

        $label = $isIdol ? '<fg=magenta>✦ Айдол создан!</>' : '<fg=cyan>✦ Пользователь создан!</>';
        $this->newLine();
        $this->line("  $label");
        $this->newLine();

        $rows = [
            ['<fg=gray>ID</>',            "<fg=white>{$user->id}</>"],
            ['<fg=gray>Имя</>',           "<fg=white>{$name}</>"],
            ['<fg=gray>Email</>',          "<fg=white>{$email}</>"],
            ['<fg=gray>Email подтверждён</>', '<fg=green>да</>'],
            ['<fg=gray>Пароль</>',         '<fg=yellow>123123</>'],
            ['<fg=gray>Пол</>',            '<fg=white>' . ($gender === 'male' ? 'Мужской' : 'Женский') . '</>'],
            ['<fg=gray>Дата рождения</>',  "<fg=white>{$birth}</>"],
        ];

        if ($isIdol) {
            $rows[] = ['<fg=gray>Рейтинг</>', '<fg=magenta>20</>'];
        }

        $this->table([], $rows);
        $this->newLine();
    }

    private function makeAdmin(): void
    {
        $faker = Factory::create('ru_RU');
        $name  = $faker->firstName() . ' ' . $faker->lastName();
        $email = 'admin_' . uniqid() . '@example.com';

        $admin = Admin::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make('123123'),
        ]);

        $this->newLine();
        $this->line('  <fg=yellow>✦ Админ создан!</>');
        $this->newLine();

        $this->table([], [
            ['<fg=gray>ID</>',     "<fg=white>{$admin->id}</>"],
            ['<fg=gray>Имя</>',    "<fg=white>{$name}</>"],
            ['<fg=gray>Email</>',  "<fg=white>{$email}</>"],
            ['<fg=gray>Пароль</>', '<fg=yellow>123123</>'],
            ['<fg=gray>Вход</>',   '<fg=white>/admin/login</>'],
        ]);

        $this->newLine();
    }
}
