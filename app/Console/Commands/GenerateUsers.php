<?php

namespace App\Console\Commands;

use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GenerateUsers extends Command
{
    protected $signature = 'users:generate {count : Количество пользователей}';
    protected $description = 'Генерирует тестовых пользователей (айдолы и обычные)';

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $faker = Factory::create('ru_RU');
        $password = Hash::make('123123');
        $now = now()->toDateTimeString();

        $usersChunk = [];
        $idolEmails = [];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $isIdol = rand(1, 100) <= 40;
            $email = uniqid('user_') . '@example.com';

            $usersChunk[] = [
                'name'                => $faker->name(),
                'email'               => $email,
                'password'            => $password,
                'email_verified_at'   => $now,
                'gender'              => ['male', 'female', null][rand(0, 2)],
                'birth_date'          => $faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
                'is_idol'             => $isIdol,
                'idol_quiz_passed_at' => $isIdol ? $now : null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            if ($isIdol) {
                $idolEmails[] = $email;
            }

            if (count($usersChunk) >= self::CHUNK_SIZE) {
                $this->flushChunk($usersChunk, $idolEmails, $now);
                $bar->advance(count($usersChunk));
                $usersChunk = [];
                $idolEmails = [];
            }
        }

        if ($usersChunk) {
            $this->flushChunk($usersChunk, $idolEmails, $now);
            $bar->advance(count($usersChunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info("Создано {$count} пользователей.");

        return self::SUCCESS;
    }

    private function flushChunk(array $users, array $idolEmails, string $now): void
    {
        DB::table('users')->insert($users);

        if (!$idolEmails) {
            return;
        }

        $idolIds = DB::table('users')->whereIn('email', $idolEmails)->pluck('id');

        DB::table('idol_applications')->insert(
            $idolIds->map(fn($id) => [
                'user_id'         => $id,
                'face_photo_path' => 'generated',
                'status'          => 'approved',
                'reviewed_at'     => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ])->all()
        );
    }
}
