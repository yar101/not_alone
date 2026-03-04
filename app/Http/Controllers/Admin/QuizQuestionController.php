<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdolQuizQuestion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizQuestionController extends Controller
{
    public function index()
    {
        $questions = IdolQuizQuestion::orderBy('stage')->orderBy('sort_order')->get()
            ->groupBy('stage')
            ->map(fn($group) => $group->values());

        return Inertia::render('Admin/Quiz/Index', [
            'questions_by_stage' => $questions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stage' => 'required|integer|min:1|max:10',
            'question' => 'required|string',
            'options' => 'required|array|min:2|max:6',
            'options.*' => 'required|string',
            'correct_option_index' => 'required|integer|min:0',
            'sort_order' => 'integer|min:0',
        ]);

        $question = IdolQuizQuestion::create($validated);

        return back()->with('success', 'Вопрос добавлен.');
    }

    public function update(Request $request, IdolQuizQuestion $question)
    {
        $validated = $request->validate([
            'stage' => 'required|integer|min:1|max:10',
            'question' => 'required|string',
            'options' => 'required|array|min:2|max:6',
            'options.*' => 'required|string',
            'correct_option_index' => 'required|integer|min:0',
            'sort_order' => 'integer|min:0',
        ]);

        $question->update($validated);

        return back()->with('success', 'Вопрос обновлён.');
    }

    public function destroy(IdolQuizQuestion $question)
    {
        $question->delete();

        return back()->with('success', 'Вопрос удалён.');
    }
}
