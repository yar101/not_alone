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
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $user->idolApplication;

        if ($user->is_idol) {
            return response()->json(['phase' => 'approved']);
        }

        if ($application) {
            if ($application->status === 'approved') {
                return response()->json(['phase' => 'approved']);
            }
            if ($application->status === 'pending') {
                return response()->json(['phase' => 'pending']);
            }
            if ($application->status === 'rejected') {
                return response()->json(['phase' => 'rejected', 'reason' => $application->rejection_reason]);
            }
        }

        if ($user->idol_quiz_passed_at) {
            return response()->json(['phase' => 'photo']);
        }

        $activeSession = $user->idolQuizSessions()->where('status', 'active')->latest()->first();
        if ($activeSession) {
            return response()->json([
                'phase' => 'quiz',
                'session' => $this->formatSession($activeSession),
            ]);
        }

        if ($user->idol_quiz_cooldown_until && now()->lt($user->idol_quiz_cooldown_until)) {
            return response()->json([
                'phase' => 'cooldown',
                'cooldown_until' => $user->idol_quiz_cooldown_until->toIso8601String(),
            ]);
        }

        return response()->json(['phase' => 'none']);
    }

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

        if ($user->idolQuizSessions()->where('status', 'active')->exists()) {
            return response()->json(['error' => 'Уже есть активная сессия'], 422);
        }

        // Determine attempt number
        $failedCount = $user->idolQuizSessions()->where('status', 'failed')->count();
        $attemptNumber = min($failedCount + 1, 2);

        // Pick one random question per stage (1-10)
        $sessionQuestions = [];
        for ($stage = 1; $stage <= 10; $stage++) {
            $q = IdolQuizQuestion::where('stage', $stage)->inRandomOrder()->first();
            if (!$q) {
                return response()->json(['error' => "Нет вопросов для этапа {$stage}"], 422);
            }
            $sessionQuestions[] = ['stage' => $stage, 'question' => $q];
        }

        $session = IdolQuizSession::create([
            'user_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'status' => 'active',
            'errors_count' => 0,
            'started_at' => now(),
        ]);

        $questionsPayload = [];
        foreach ($sessionQuestions as $item) {
            $sq = IdolQuizSessionQuestion::create([
                'session_id' => $session->id,
                'question_id' => $item['question']->id,
                'stage' => $item['stage'],
            ]);
            $questionsPayload[] = [
                'stage' => $item['stage'],
                'question_id' => $item['question']->id,
                'question' => $item['question']->question,
                'options' => $item['question']->options,
            ];
        }

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

        if (!$isCorrect) {
            $session->increment('errors_count');
            $session->refresh();
        }

        $result = [
            'correct' => $isCorrect,
            'correct_index' => $question->correct_option_index,
            'errors_count' => $session->errors_count,
        ];

        // If last stage, finalize session
        if ($validated['stage'] === 10) {
            $session->refresh();
            $passed = $session->errors_count <= 2;

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

    private function formatSession(IdolQuizSession $session): array
    {
        $answeredStages = $session->questions()->whereNotNull('answered_at')->pluck('stage')->toArray();
        $nextStage = empty($answeredStages) ? 1 : (max($answeredStages) + 1);

        $question = $session->questions()->where('stage', $nextStage)->with('question')->first();

        return [
            'id' => $session->id,
            'attempt_number' => $session->attempt_number,
            'errors_count' => $session->errors_count,
            'answered_stages' => $answeredStages,
            'current_stage' => $nextStage,
            'current_question' => $question ? [
                'stage' => $question->stage,
                'question_id' => $question->question_id,
                'question' => $question->question->question,
                'options' => $question->question->options,
            ] : null,
            'all_questions' => $session->questions()->with('question')->get()->map(fn($sq) => [
                'stage' => $sq->stage,
                'question_id' => $sq->question_id,
                'question' => $sq->question->question,
                'options' => $sq->question->options,
            ])->toArray(),
        ];
    }
}
