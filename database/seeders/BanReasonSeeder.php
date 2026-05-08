<?php

namespace Database\Seeders;

use App\Models\BanReason;
use Illuminate\Database\Seeder;

class BanReasonSeeder extends Seeder
{
    public function run(): void
    {
        $chatBlockReasons = [
            'Спам',
            'Оскорбления',
            'Нежелательный контент',
            'Агрессивное поведение',
            'Домогательства',
            'Другое',
        ];

        foreach ($chatBlockReasons as $i => $label) {
            BanReason::updateOrCreate(
                ['label' => $label, 'type' => 'chat_block'],
                ['sort_order' => $i]
            );
        }

        $userBanReasons = [
            'Нарушение правил сообщества',
            'Оскорбления и угрозы',
            'Спам',
            'Мошенничество',
            'Другое',
        ];

        foreach ($userBanReasons as $i => $label) {
            BanReason::updateOrCreate(
                ['label' => $label, 'type' => 'user_ban'],
                ['sort_order' => $i]
            );
        }
    }
}
