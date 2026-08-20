<?php

namespace Database\Seeders;

use App\Models\IdolArticleVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class IdolArticleSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('data/idol_article.html');
        $html = File::exists($filePath) ? File::get($filePath) : '';

        if (empty($html)) {
            return;
        }

        $active = IdolArticleVersion::active()->first();

        if (! $active) {
            IdolArticleVersion::create([
                'html' => $html,
                'label' => 'Базовая памятка для кандидатов',
                'is_active' => true,
            ]);
        }
    }
}
