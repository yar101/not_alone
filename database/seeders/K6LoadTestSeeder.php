<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Service;
use App\Models\Conversation;
use App\Models\IdolApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

class K6LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1. Создаём бота-клиента
        $bot = User::firstOrCreate(
            ['email' => 'k6_bot@noalone.test'],
            [
                'name' => 'K6 Load Bot',
                'password' => Hash::make('password'),
                'is_idol' => false,
                'email_verified_at' => $now,
                'gender' => 'male',
                'birth_date' => '2000-01-01',
            ]
        );

        // 2. Создаём бота-айдола
        $idol = User::firstOrCreate(
            ['email' => 'k6_idol@noalone.test'],
            [
                'name' => 'K6 Idol Bot',
                'password' => Hash::make('password'),
                'is_idol' => true,
                'email_verified_at' => $now,
                'gender' => 'female',
                'birth_date' => '2000-01-01',
                'rating' => 20,
                'idol_quiz_passed_at' => $now,
            ]
        );

        // 3. Создаём заявку для айдола (чтобы он считался активным)
        IdolApplication::firstOrCreate(
            ['user_id' => $idol->id],
            [
                'face_photo_path' => 'generated',
                'status' => 'approved',
                'reviewed_at' => $now,
            ]
        );

        // 4. Создаём тестовую услугу для айдола
        $service = Service::firstOrCreate(
            ['user_id' => $idol->id],
            [
                'name' => ['ru' => 'Тестовая услуга K6'],
                'category_id' => 1,
                'time_unit_id' => 1,
                'price' => 100,
                'is_active' => true,
            ]
        );

        // 5. Инициализируем диалог между ними
        $conversation = Conversation::findOrCreateBetween($idol, $bot);

        // 6. Сохраняем ID сущностей в файл
        $envData = implode("\n", [
            "K6_IDOL_ID={$idol->id}",
            "K6_SERVICE_ID={$service->id}",
            "K6_CONV_ID={$conversation->id}",
        ]);
        
        File::put(base_path('.env.k6'), $envData);
        
        $this->command->info("K6 Test Data seeded successfully!");
    }
}
