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

    private const LANGUAGES = ['ru', 'en', 'zh', 'ko', 'ja', 'de', 'fr', 'es', 'pt', 'tr'];

    private \Faker\Generator $fakerEn;

    public function handle(): int
    {
        $count    = (int) $this->argument('count');
        $faker    = Factory::create('ru_RU');
        $this->fakerEn = Factory::create('en_US');
        $password = Hash::make('123123');
        $now      = now()->toDateTimeString();

        $traitIds    = DB::table('traits')->pluck('id')->all();
        $interestIds = DB::table('interests')->pluck('id')->all();

        $usersChunk = [];
        $idolEmails = [];
        $allEmails  = [];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $isIdol = rand(1, 100) <= 40;
            $email  = uniqid('user_') . '@example.com';

            $usersChunk[] = [
                'name'                => $this->generateName($faker),
                'email'               => $email,
                'password'            => $password,
                'email_verified_at'   => $now,
                'gender'              => ['male', 'female'][rand(0, 1)],
                'birth_date'          => $faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
                'about'               => $faker->realText(rand(80, 200)),
                'avatar_path'         => null,
                'is_idol'             => $isIdol,
                'rating'              => $isIdol ? rand(15, 60) : rand(10, 45),
                'idol_quiz_passed_at' => $isIdol ? $now : null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            $allEmails[] = $email;
            if ($isIdol) {
                $idolEmails[] = $email;
            }

            if (count($usersChunk) >= self::CHUNK_SIZE) {
                $advance = count($usersChunk);
                $this->flushChunk($usersChunk, $idolEmails, $allEmails, $now, $traitIds, $interestIds, $faker);
                $bar->advance($advance);
                $usersChunk = [];
                $idolEmails = [];
                $allEmails  = [];
            }
        }

        if ($usersChunk) {
            $advance = count($usersChunk);
            $this->flushChunk($usersChunk, $idolEmails, $allEmails, $now, $traitIds, $interestIds, $faker);
            $bar->advance($advance);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Создано {$count} пользователей.");

        return self::SUCCESS;
    }

    private function flushChunk(
        array $users,
        array $idolEmails,
        array $allEmails,
        string $now,
        array $traitIds,
        array $interestIds,
        \Faker\Generator $faker
    ): void {
        DB::table('users')->insert($users);

        $insertedMap = DB::table('users')
            ->whereIn('email', $allEmails)
            ->pluck('id', 'email');

        // idol_applications
        if ($idolEmails) {
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

        // user_traits
        if ($traitIds) {
            $rows = [];
            foreach ($insertedMap as $id) {
                $picked = array_rand(array_flip($traitIds), rand(3, min(7, count($traitIds))));
                foreach ((array) $picked as $tid) {
                    $rows[] = ['user_id' => $id, 'trait_id' => $tid];
                }
            }
            if ($rows) DB::table('user_traits')->insert($rows);
        }

        // user_interests
        if ($interestIds) {
            $rows = [];
            foreach ($insertedMap as $id) {
                $picked = array_rand(array_flip($interestIds), rand(3, min(6, count($interestIds))));
                foreach ((array) $picked as $iid) {
                    $rows[] = ['user_id' => $id, 'interest_id' => $iid];
                }
            }
            if ($rows) DB::table('user_interests')->insert($rows);
        }

        // user_languages
        $rows = [];
        foreach ($insertedMap as $id) {
            $langCount = rand(1, 3);
            $langs = (array) array_rand(array_flip(self::LANGUAGES), $langCount);
            foreach ($langs as $code) {
                $rows[] = ['user_id' => $id, 'language_code' => $code, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows) DB::table('user_languages')->insert($rows);

        // posts (1–4 per user)
        $rows = [];
        foreach ($insertedMap as $id) {
            $postCount = rand(1, 4);
            for ($p = 0; $p < $postCount; $p++) {
                $rows[] = [
                    'user_id'    => $id,
                    'body'       => mb_substr($faker->realText(rand(40, 260)), 0, 277),
                    'photo_path' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if ($rows) DB::table('posts')->insert($rows);
    }

    private function generateName(\Faker\Generator $fakerRu): string
    {
        $style = rand(0, 4);
        return match ($style) {
            0 => $fakerRu->firstNameMale(),
            1 => $fakerRu->firstNameFemale(),
            2 => $fakerRu->firstNameMale() . ' ' . $fakerRu->lastNameMale(),
            3 => $this->fakerEn->firstName(),
            4 => $this->fakerEn->userName(),
        };
    }
}
