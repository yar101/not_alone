<?php

namespace Database\Seeders;

use App\Models\BanReason;
use Illuminate\Database\Seeder;

class BanReasonSeeder extends Seeder
{
    public function run(): void
    {
        // Clean table to prevent duplicates
        BanReason::query()->delete();

        $chatBlockReasons = [
            ['ru' => 'Спам', 'en' => 'Spam'],
            ['ru' => 'Оскорбления', 'en' => 'Insults'],
            ['ru' => 'Нежелательный контент', 'en' => 'Unwanted content'],
            ['ru' => 'Агрессивное поведение', 'en' => 'Aggressive behavior'],
            ['ru' => 'Домогательства', 'en' => 'Harassment'],
            ['ru' => 'Другое', 'en' => 'Other'],
        ];

        foreach ($chatBlockReasons as $i => $label) {
            $reason = new BanReason();
            $reason->type = 'chat_block';
            $reason->sort_order = $i;
            $reason->setTranslation('label', 'ru', $label['ru']);
            $reason->setTranslation('label', 'en', $label['en']);
            $reason->save();
        }

        $userBanReasons = [
            ['ru' => 'Нарушение правил сообщества', 'en' => 'Violation of community rules'],
            ['ru' => 'Оскорбления и угрозы', 'en' => 'Insults and threats'],
            ['ru' => 'Спам', 'en' => 'Spam'],
            ['ru' => 'Мошенничество', 'en' => 'Fraud'],
            ['ru' => 'Другое', 'en' => 'Other'],
        ];

        foreach ($userBanReasons as $i => $label) {
            $reason = new BanReason();
            $reason->type = 'user_ban';
            $reason->sort_order = $i;
            $reason->setTranslation('label', 'ru', $label['ru']);
            $reason->setTranslation('label', 'en', $label['en']);
            $reason->save();
        }
    }
}
