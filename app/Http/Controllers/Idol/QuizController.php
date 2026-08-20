<?php

namespace App\Http\Controllers\Idol;

use App\Http\Controllers\Controller;
use App\Models\IdolQuizQuestion;
use App\Models\IdolQuizSession;
use App\Models\IdolQuizSessionQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_idol || ($user->idolApplication && in_array($user->idolApplication->status, ['pending', 'approved']))) {
            return response()->json(['error' => 'Недопустимо'], 422);
        }

        if ($user->idol_quiz_cooldown_until && now()->lt($user->idol_quiz_cooldown_until)) {
            return response()->json([
                'error' => 'Кулдаун активен',
                'cooldown_until' => $user->idol_quiz_cooldown_until->toIso8601String(),
            ], 422);
        }

        // Закрываем зависшие активные сессии (не должны существовать, но на случай сбоя)
        $user->idolQuizSessions()->where('status', 'active')->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        // Determine attempt number
        $failedCount = $user->idolQuizSessions()->where('status', 'failed')->count();
        $attemptNumber = min($failedCount + 1, 2);

        // Pick one random question per stage (1-10) using a single partitioned query
        $questions = IdolQuizQuestion::query()
            ->fromSub(function ($query) {
                $query->from('idol_quiz_questions')
                    ->select('*')
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY stage ORDER BY RANDOM()) as rn')
                    ->whereBetween('stage', [1, 10]);
            }, 'ranked_questions')
            ->where('rn', 1)
            ->orderBy('stage')
            ->get();

        if ($questions->count() < 10) {
            return response()->json(['error' => 'Недостаточно вопросов для формирования теста.'], 422);
        }

        $now = now();
        $session = IdolQuizSession::create([
            'user_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'status' => 'active',
            'errors_count' => 0,
            'started_at' => $now,
        ]);

        $sessionQuestionRecords = $questions->map(fn ($q) => [
            'session_id' => $session->id,
            'question_id' => $q->id,
            'stage' => $q->stage,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        IdolQuizSessionQuestion::insert($sessionQuestionRecords);

        $questionsPayload = $questions->map(fn ($q) => [
            'stage' => $q->stage,
            'question_id' => $q->id,
            'question' => $q->question,
            'options' => $q->options,
        ])->all();

        return response()->json([
            'session_id' => $session->id,
            'attempt_number' => $attemptNumber,
            'questions' => $questionsPayload,
        ]);
    }

    public function answer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer',
            'stage' => 'required|integer|min:1|max:10',
            'answer_index' => 'required|integer|min:0',
        ]);

        $user = $request->user();

        $session = IdolQuizSession::where('id', $validated['session_id'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Enforce sequential stage answering
        $unansweredPrevious = IdolQuizSessionQuestion::where('session_id', $session->id)
            ->where('stage', '<', $validated['stage'])
            ->whereNull('answered_at')
            ->exists();

        if ($unansweredPrevious) {
            return response()->json(['error' => 'Предыдущие этапы должны быть пройдены последовательно.'], 422);
        }

        $sessionQuestion = IdolQuizSessionQuestion::where('session_id', $session->id)
            ->where('stage', $validated['stage'])
            ->whereNull('answered_at')
            ->firstOrFail();

        $question = $sessionQuestion->question;
        $isCorrect = $validated['answer_index'] === $question->correct_option_index;

        $sessionQuestion->update([
            'user_answer_index' => $validated['answer_index'],
            'is_correct' => $isCorrect,
            'answered_at' => now(),
        ]);

        if (! $isCorrect) {
            $session->increment('errors_count');
            $session->refresh();
        }

        $result = [
            'correct' => $isCorrect,
            'correct_index' => $question->correct_option_index,
            'errors_count' => $session->errors_count,
        ];

        $failedEarly = $session->errors_count > 2;
        $totalAnswered = IdolQuizSessionQuestion::where('session_id', $session->id)
            ->whereNotNull('answered_at')
            ->count();
        $isLastStage = ($validated['stage'] === 10 && $totalAnswered === 10);

        if ($failedEarly || $isLastStage) {
            $passed = ! $failedEarly && $totalAnswered === 10;

            if ($passed) {
                $session->update(['status' => 'passed', 'completed_at' => now()]);
                $user->update(['idol_quiz_passed_at' => now()]);
                $result['session_status'] = 'passed';
            } else {
                $session->update(['status' => 'failed', 'completed_at' => now()]);
                if ($session->attempt_number >= 2) {
                    $user->update(['idol_quiz_cooldown_until' => now()->addHours(24)]);
                    $result['cooldown_until'] = $user->fresh()->idol_quiz_cooldown_until->toIso8601String();
                }
                $result['session_status'] = 'failed';
                $result['attempt_number'] = $session->attempt_number;
            }
        }

        return response()->json($result);
    }
}
