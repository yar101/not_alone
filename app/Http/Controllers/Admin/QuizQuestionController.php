<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdolArticleVersion;
use App\Models\IdolQuizQuestion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizQuestionController extends Controller
{
    // ═══════════════════════════════════════════════════════
    // Quiz questions CRUD
    // ═══════════════════════════════════════════════════════

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
            'stage'                => 'required|integer|min:1|max:10',
            'question'             => 'required|string',
            'options'              => 'required|array|min:2|max:6',
            'options.*'            => 'required|string',
            'correct_option_index' => 'required|integer|min:0',
            'sort_order'           => 'integer|min:0',
        ]);

        IdolQuizQuestion::create($validated);

        return back()->with('success', 'Вопрос добавлен.');
    }

    public function update(Request $request, IdolQuizQuestion $question)
    {
        $validated = $request->validate([
            'stage'                => 'required|integer|min:1|max:10',
            'question'             => 'required|string',
            'options'              => 'required|array|min:2|max:6',
            'options.*'            => 'required|string',
            'correct_option_index' => 'required|integer|min:0',
            'sort_order'           => 'integer|min:0',
        ]);

        $question->update($validated);

        return back()->with('success', 'Вопрос обновлён.');
    }

    public function destroy(IdolQuizQuestion $question)
    {
        $question->delete();

        return back()->with('success', 'Вопрос удалён.');
    }

    // ═══════════════════════════════════════════════════════
    // Questions JSON export / import
    // ═══════════════════════════════════════════════════════

    /**
     * Export all questions as a structured JSON file.
     */
    public function exportQuestions()
    {
        $questions = IdolQuizQuestion::orderBy('stage')
            ->orderBy('sort_order')
            ->get(['stage', 'question', 'options', 'correct_option_index', 'sort_order'])
            ->map(fn($q) => [
                'stage'                => $q->stage,
                'question'             => $q->question,
                'options'              => $q->options,
                'correct_option_index' => $q->correct_option_index,
                'sort_order'           => $q->sort_order,
            ])
            ->values();

        $payload = [
            'version'   => 1,
            'exported_at' => now()->toISOString(),
            'questions' => $questions,
        ];

        $date = now()->format('Y-m-d');

        return response()->json($payload, 200, [
            'Content-Disposition' => "attachment; filename=\"quiz_questions_{$date}.json\"",
            'Content-Type'        => 'application/json; charset=utf-8',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Download a blank/template JSON file for filling out with questions.
     */
    public function downloadTemplate()
    {
        $payload = [
            'version'   => 1,
            'questions' => [
                [
                    'stage'                => 1,
                    'question'             => 'Пример вопроса для этапа 1?',
                    'options'              => [
                        'Вариант ответа A',
                        'Вариант ответа B',
                        'Вариант ответа C',
                        'Вариант ответа D'
                    ],
                    'correct_option_index' => 0,
                    'sort_order'           => 0,
                ],
                [
                    'stage'                => 2,
                    'question'             => 'Пример вопроса для этапа 2?',
                    'options'              => [
                        'Вариант ответа A',
                        'Вариант ответа B',
                        'Вариант ответа C'
                    ],
                    'correct_option_index' => 1,
                    'sort_order'           => 0,
                ]
            ],
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="quiz_questions_template.json"',
            'Content-Type'        => 'application/json; charset=utf-8',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Import questions from a JSON file.
     *
     * Modes:
     *   replace — delete all existing questions and insert from file
     *   append  — keep existing, add only questions from file (duplicates by text skipped)
     */
    public function importQuestions(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:2048',
            'mode' => 'required|in:replace,append',
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['file' => 'Файл не является валидным JSON.']);
        }

        $questions = $data['questions'] ?? (is_array($data) && isset($data[0]) ? $data : null);

        if (!is_array($questions) || empty($questions)) {
            return back()->withErrors(['file' => 'Файл не содержит массив вопросов (ключ "questions").']);
        }

        // Validate every question entry
        $errors = [];
        foreach ($questions as $i => $q) {
            $n = $i + 1;
            if (!isset($q['stage']) || !is_int((int) $q['stage']) || $q['stage'] < 1 || $q['stage'] > 10) {
                $errors[] = "Вопрос #{$n}: поле «stage» должно быть числом от 1 до 10.";
            }
            if (empty($q['question']) || !is_string($q['question'])) {
                $errors[] = "Вопрос #{$n}: поле «question» отсутствует или не является строкой.";
            }
            if (!isset($q['options']) || !is_array($q['options']) || count($q['options']) < 2) {
                $errors[] = "Вопрос #{$n}: поле «options» должно быть массивом минимум из 2 вариантов.";
            }
            if (!isset($q['correct_option_index']) || !is_numeric($q['correct_option_index'])) {
                $errors[] = "Вопрос #{$n}: поле «correct_option_index» отсутствует или не является числом.";
            }
            if (count($errors) >= 5) {
                $errors[] = '…(и другие ошибки)';
                break;
            }
        }

        if ($errors) {
            return back()->withErrors(['file' => implode(' | ', $errors)]);
        }

        $mode = $request->input('mode', 'append');

        \DB::transaction(function () use ($questions, $mode) {
            if ($mode === 'replace') {
                IdolQuizQuestion::truncate();
            }

            foreach ($questions as $q) {
                $attrs = [
                    'stage'                => (int) $q['stage'],
                    'question'             => trim($q['question']),
                    'options'              => array_values(array_map('strval', $q['options'])),
                    'correct_option_index' => (int) $q['correct_option_index'],
                    'sort_order'           => isset($q['sort_order']) ? (int) $q['sort_order'] : 0,
                ];

                if ($mode === 'append') {
                    IdolQuizQuestion::firstOrCreate(
                        ['stage' => $attrs['stage'], 'question' => $attrs['question']],
                        $attrs
                    );
                } else {
                    IdolQuizQuestion::create($attrs);
                }
            }
        });

        $count = count($questions);
        $label = $mode === 'replace' ? "Все вопросы заменены ({$count} шт.)" : "Добавлено/пропущено: {$count} вопросов.";

        return back()->with('success', $label);
    }

    // ═══════════════════════════════════════════════════════
    // Article versioning
    // ═══════════════════════════════════════════════════════

    public function showArticle()
    {
        $active   = IdolArticleVersion::active()->first();
        $versions = IdolArticleVersion::orderByDesc('created_at')
            ->get(['id', 'label', 'is_active', 'created_at']);

        return Inertia::render('Admin/Quiz/Article', [
            'active_version' => $active,
            'versions'       => $versions,
        ]);
    }

    /**
     * Save a new version (named or auto).
     * Auto-versions are purged to keep only the last 10.
     */
    public function storeVersion(Request $request)
    {
        $validated = $request->validate([
            'html'  => 'nullable|string',
            'label' => 'nullable|string|max:120',
        ]);

        $html  = $validated['html'] ?? '';
        $label = filled($validated['label']) ? trim($validated['label']) : null;

        $version = IdolArticleVersion::create([
            'html'      => $html,
            'label'     => $label,
            'is_active' => false,
        ]);

        // Purge old auto-versions (keep 10)
        IdolArticleVersion::purgeUnlabeled(10);

        return back()->with('success', 'Версия сохранена.');
    }

    /**
     * Get a single version (including HTML).
     */
    public function getVersion(IdolArticleVersion $version)
    {
        return response()->json($version);
    }

    /**
     * Activate a version — becomes what users see on idol/apply.
     * Current editor content is saved first as an auto-version if it differs.
     */
    public function activateVersion(Request $request, IdolArticleVersion $version)
    {
        $version->activate();

        return back()->with('success', 'Версия активирована.');
    }

    /**
     * Update the currently active version in place.
     */
    public function updateActiveVersion(Request $request)
    {
        $validated = $request->validate([
            'html' => 'nullable|string',
        ]);

        $html = $validated['html'] ?? '';

        $active = IdolArticleVersion::active()->first();

        if ($active) {
            $active->update(['html' => $html]);
            return back()->with('success', 'Изменения в активной версии сохранены.');
        }

        IdolArticleVersion::create([
            'html'      => $html,
            'label'     => 'Изначальная версия',
            'is_active' => true,
        ]);

        return back()->with('success', 'Создана новая активная версия.');
    }

    /**
     * Delete a version. Cannot delete the active one.
     */
    public function destroyVersion(IdolArticleVersion $version)
    {
        if ($version->is_active) {
            return back()->withErrors(['version' => 'Нельзя удалить активную версию.']);
        }

        $version->delete();

        return back()->with('success', 'Версия удалена.');
    }

    /**
     * Return a line-by-line diff between two versions.
     */
    public function diffVersions(IdolArticleVersion $version, IdolArticleVersion $other)
    {
        $oldLines = explode("\n", $other->html);
        $newLines = explode("\n", $version->html);

        $diff = $this->computeDiff($oldLines, $newLines);

        return response()->json([
            'version_a' => ['id' => $other->id, 'label' => $other->label, 'created_at' => $other->created_at],
            'version_b' => ['id' => $version->id, 'label' => $version->label, 'created_at' => $version->created_at],
            'diff'      => $diff,
        ]);
    }

    /**
     * Export active version as an HTML file download.
     */
    public function exportArticle()
    {
        $active = IdolArticleVersion::active()->firstOrFail();
        $date   = now()->format('Y-m-d');

        return response($active->html, 200, [
            'Content-Type'        => 'text/html; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"article_{$date}.html\"",
        ]);
    }

    /**
     * Import an HTML file and create a new version from it.
     */
    public function importArticle(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:html,htm|max:2048',
        ]);

        $html  = file_get_contents($request->file('file')->getRealPath());
        $label = 'Импорт от ' . now()->format('d.m.Y H:i');

        IdolArticleVersion::create([
            'html'      => $html,
            'label'     => $label,
            'is_active' => false,
        ]);

        return back()->with('success', 'Файл импортирован как новая версия.');
    }

    // ── Private: simple LCS-based line diff ────────────────
    private function computeDiff(array $old, array $new): array
    {
        $result = [];
        $matrix = [];
        $n      = count($old);
        $m      = count($new);

        // Build LCS matrix
        for ($i = 0; $i <= $n; $i++) {
            $matrix[$i] = array_fill(0, $m + 1, 0);
        }
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $matrix[$i][$j] = ($old[$i - 1] === $new[$j - 1])
                    ? $matrix[$i - 1][$j - 1] + 1
                    : max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
            }
        }

        // Backtrack
        $i = $n;
        $j = $m;
        $chunks = [];
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $old[$i - 1] === $new[$j - 1]) {
                array_unshift($chunks, ['type' => ' ', 'text' => $old[$i - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $matrix[$i][$j - 1] >= $matrix[$i - 1][$j])) {
                array_unshift($chunks, ['type' => '+', 'text' => $new[$j - 1]]);
                $j--;
            } else {
                array_unshift($chunks, ['type' => '-', 'text' => $old[$i - 1]]);
                $i--;
            }
        }

        return $chunks;
    }
}
