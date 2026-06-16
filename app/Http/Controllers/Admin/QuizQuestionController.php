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
