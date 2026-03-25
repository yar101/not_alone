<?php

namespace App\Console\Commands;

use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GenerateUsers extends Command
{
    protected $signature   = 'users:generate {count : Количество пользователей}';
    protected $description = 'Генерирует тестовых пользователей (айдолы и обычные)';

    private const CHUNK_SIZE = 100;
    private const LANGUAGES  = ['ru', 'en', 'zh', 'ko', 'ja', 'de', 'fr', 'es', 'pt', 'tr'];

    private \Faker\Generator $fakerEn;

    public function handle(): int
    {
        $count         = (int) $this->argument('count');
        $faker         = Factory::create('ru_RU');
        $this->fakerEn = Factory::create('en_US');
        $password      = Hash::make('123123');
        $now           = now()->toDateTimeString();

        $traitIds    = DB::table('traits')->pluck('id')->all();
        $interestIds = DB::table('interests')->pluck('id')->all();

        // Тексты генерируем один раз — потом просто берём array_rand
        $texts = [
            'about'   => array_map(fn() => $faker->realText(rand(80, 200)),  range(1, 30)),
            'post'    => array_map(fn() => mb_substr($faker->realText(rand(40, 300)), 0, 377), range(1, 30)),
            'comment' => array_map(fn() => mb_substr($faker->realText(rand(15, 150)), 0, 177), range(1, 30)),
            'reply'   => array_map(fn() => mb_substr($faker->realText(rand(10, 100)), 0, 177), range(1, 30)),
        ];

        $usersChunk = [];
        $idolEmails = [];
        $allEmails  = [];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $isIdol = rand(1, 100) <= 40;
            $uid    = uniqid();
            $email  = 'user_' . $uid . '@example.com';

            $usersChunk[] = [
                'name'                => $this->generateName($faker) . '_' . $uid,
                'email'               => $email,
                'password'            => $password,
                'email_verified_at'   => $now,
                'gender'              => ['male', 'female'][rand(0, 1)],
                'birth_date'          => $faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
                'about'               => $texts['about'][array_rand($texts['about'])],
                'avatar_path'         => null,
                'is_idol'             => $isIdol,
                'rating'              => $isIdol ? rand(15, 60) : rand(10, 45),
                'idol_quiz_passed_at' => $isIdol ? $now : null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            $allEmails[] = $email;
            if ($isIdol) $idolEmails[] = $email;

            if (count($usersChunk) >= self::CHUNK_SIZE) {
                $advance = count($usersChunk);
                $this->flushChunk($usersChunk, $idolEmails, $allEmails, $now, $traitIds, $interestIds, $texts);
                $bar->advance($advance);
                $usersChunk = $idolEmails = $allEmails = [];
            }
        }

        if ($usersChunk) {
            $this->flushChunk($usersChunk, $idolEmails, $allEmails, $now, $traitIds, $interestIds, $texts);
            $bar->advance(count($usersChunk));
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
        array $texts,
    ): void {
        DB::table('users')->insert($users);

        $insertedMap = DB::table('users')->whereIn('email', $allEmails)->pluck('id', 'email');
        $userIds     = array_values($insertedMap->all());

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
            foreach ($userIds as $id) {
                $picked = array_rand(array_flip($traitIds), rand(3, min(7, count($traitIds))));
                foreach ((array) $picked as $tid) $rows[] = ['user_id' => $id, 'trait_id' => $tid];
            }
            if ($rows) DB::table('user_traits')->insert($rows);
        }

        // user_interests
        if ($interestIds) {
            $rows = [];
            foreach ($userIds as $id) {
                $picked = array_rand(array_flip($interestIds), rand(3, min(6, count($interestIds))));
                foreach ((array) $picked as $iid) $rows[] = ['user_id' => $id, 'interest_id' => $iid];
            }
            if ($rows) DB::table('user_interests')->insert($rows);
        }

        // user_languages
        $rows = [];
        foreach ($userIds as $id) {
            $langs = (array) array_rand(array_flip(self::LANGUAGES), rand(1, 3));
            foreach ($langs as $code)
                $rows[] = ['user_id' => $id, 'language_code' => $code, 'created_at' => $now, 'updated_at' => $now];
        }
        if ($rows) DB::table('user_languages')->insert($rows);

        // posts (10–20 на пользователя)
        $postRows = [];
        foreach ($userIds as $id) {
            for ($p = 0, $n = rand(10, 20); $p < $n; $p++) {
                $postRows[] = [
                    'user_id'    => $id,
                    'body'       => $texts['post'][array_rand($texts['post'])],
                    'photo_path' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (!$postRows) return;
        DB::table('posts')->insert($postRows);

        $postIds = DB::table('posts')->whereIn('user_id', $userIds)->pluck('id')->all();

        // likes (0–5 на пост)
        $likeRows  = [];
        $likesSeen = [];
        foreach ($postIds as $postId) {
            $n = rand(0, min(5, count($userIds)));
            if ($n === 0) continue;
            $likers = (array) array_rand(array_flip($userIds), $n);
            foreach ($likers as $uid) {
                $key = "{$postId}_{$uid}";
                if (!isset($likesSeen[$key])) {
                    $likesSeen[$key] = true;
                    $likeRows[] = ['post_id' => $postId, 'user_id' => $uid, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        if ($likeRows) DB::table('post_likes')->insert($likeRows);

        // comments (0–4 top-level на пост)
        $commentRows = [];
        foreach ($postIds as $postId) {
            for ($c = 0, $n = rand(0, 4); $c < $n; $c++) {
                $commentRows[] = [
                    'post_id'    => $postId,
                    'user_id'    => $userIds[array_rand($userIds)],
                    'parent_id'  => null,
                    'body'       => $texts['comment'][array_rand($texts['comment'])],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (!$commentRows) return;
        DB::table('post_comments')->insert($commentRows);

        // replies (0–2 на каждый top-level комментарий)
        $topComments = DB::table('post_comments')
            ->whereIn('post_id', $postIds)->whereNull('parent_id')
            ->select('id', 'post_id')->get();

        $replyRows = [];
        foreach ($topComments as $cmt) {
            for ($r = 0, $n = rand(0, 2); $r < $n; $r++) {
                $replyRows[] = [
                    'post_id'    => $cmt->post_id,
                    'user_id'    => $userIds[array_rand($userIds)],
                    'parent_id'  => $cmt->id,
                    'body'       => $texts['reply'][array_rand($texts['reply'])],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if ($replyRows) DB::table('post_comments')->insert($replyRows);
    }

    private function generateName(\Faker\Generator $fakerRu): string
    {
        return match (rand(0, 4)) {
            0 => $fakerRu->firstNameMale(),
            1 => $fakerRu->firstNameFemale(),
            2 => $fakerRu->firstNameMale() . ' ' . $fakerRu->lastNameMale(),
            3 => $this->fakerEn->firstName(),
            4 => $this->fakerEn->userName(),
        };
    }
}
