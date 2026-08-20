<?php

namespace Database\Seeders;

use App\Models\IdolQuizQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class QuizQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('data/quiz_questions.json');

        if (! File::exists($filePath)) {
            return;
        }

        $json = json_decode(File::get($filePath), true);
        $questions = $json['questions'] ?? (is_array($json) && isset($json[0]) ? $json : []);

        foreach ($questions as $data) {
            IdolQuizQuestion::updateOrCreate(
                [
                    'stage' => (int) $data['stage'],
                    'question' => trim($data['question']),
                ],
                [
                    'options' => array_values(array_map('strval', $data['options'])),
                    'correct_option_index' => (int) $data['correct_option_index'],
                    'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
                ]
            );
        }
    }
}
