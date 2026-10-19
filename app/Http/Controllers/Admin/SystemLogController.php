<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logPath = storage_path('logs/laravel.log');
        $fileSize = file_exists($logPath) ? filesize($logPath) : 0;
        $fileSizeFormatted = $this->formatBytes($fileSize);
        $lastModified = file_exists($logPath) ? date('d.m.Y H:i:s', filemtime($logPath)) : null;

        $levelFilter = strtolower((string) $request->get('level', 'all'));
        $search = trim((string) $request->get('search', ''));

        $entries = $this->parseLogFile($logPath);

        // Apply filters
        if ($levelFilter !== 'all') {
            $entries = array_filter($entries, function ($entry) use ($levelFilter) {
                $lvl = strtolower($entry['level']);
                if ($levelFilter === 'error') {
                    return in_array($lvl, ['error', 'critical', 'alert', 'emergency'], true);
                }
                if ($levelFilter === 'warning') {
                    return in_array($lvl, ['warning', 'warn'], true);
                }
                if ($levelFilter === 'info') {
                    return in_array($lvl, ['info', 'notice'], true);
                }
                if ($levelFilter === 'debug') {
                    return $lvl === 'debug';
                }
                return $lvl === $levelFilter;
            });
        }

        if ($search !== '') {
            $entries = array_filter($entries, function ($entry) use ($search) {
                return mb_stripos($entry['message'], $search) !== false
                    || mb_stripos($entry['trace'], $search) !== false
                    || mb_stripos($entry['date'], $search) !== false;
            });
        }

        // Stats by level
        $allEntries = $this->parseLogFile($logPath);
        $levelCounts = [
            'total' => count($allEntries),
            'error' => 0,
            'warning' => 0,
            'info' => 0,
            'debug' => 0,
        ];
        foreach ($allEntries as $e) {
            $lvl = strtolower($e['level']);
            if (in_array($lvl, ['error', 'critical', 'alert', 'emergency'], true)) {
                $levelCounts['error']++;
            } elseif (in_array($lvl, ['warning', 'warn'], true)) {
                $levelCounts['warning']++;
            } elseif (in_array($lvl, ['info', 'notice'], true)) {
                $levelCounts['info']++;
            } elseif ($lvl === 'debug') {
                $levelCounts['debug']++;
            }
        }

        // Paginate manually
        $page = (int) $request->get('page', 1);
        $perPage = 50;
        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        $pagedData = array_values(array_slice($entries, $offset, $perPage));

        $paginator = new LengthAwarePaginator(
            $pagedData,
            $total,
            $perPage,
            $page,
            ['path' => route('admin.system-logs.index'), 'query' => $request->query()]
        );

        return Inertia::render('Admin/SystemLogs/Index', [
            'logs' => $paginator,
            'filters' => [
                'level' => $levelFilter,
                'search' => $search,
            ],
            'stats' => [
                'counts' => $levelCounts,
                'file_size' => $fileSizeFormatted,
                'last_modified' => $lastModified,
            ],
        ]);
    }

    public function clear(): RedirectResponse
    {
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }

        AdminLogService::log(
            auth('admin')->id() ?? 0,
            'clear_system_logs',
            'system_log',
            0,
            ['cleared_at' => now()->toIso8601String()]
        );

        return back()->with('success', 'Системный лог успешно очищен.');
    }

    /**
     * Parse the log file into structured entries.
     */
    private function parseLogFile(string $filePath): array
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            return [];
        }

        // Read last ~5000 lines to avoid memory overflow on very large log files
        $lines = $this->readTailLines($filePath, 5000);
        if (empty($lines)) {
            return [];
        }

        $entries = [];
        $current = null;
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] ([a-zA-Z0-9_-]+)\.([A-Z]+): (.*)$/';

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'id' => md5($matches[1] . $matches[3] . substr($matches[4], 0, 50) . count($entries)),
                    'date' => $matches[1],
                    'env' => $matches[2],
                    'level' => strtoupper($matches[3]),
                    'message' => $matches[4],
                    'trace' => '',
                ];
            } elseif ($current !== null) {
                $current['trace'] .= ($current['trace'] === '' ? '' : "\n") . $line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        // Return latest first
        return array_reverse($entries);
    }

    /**
     * Efficiently read the last N lines of a file.
     */
    private function readTailLines(string $filePath, int $maxLines): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        $lines = [];
        $buffer = '';
        $chunkSize = 4096;

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);
        $pos = $fileSize;

        while ($pos > 0 && count($lines) < $maxLines) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize);
            $buffer = $chunk . $buffer;

            $chunkLines = explode("\n", $buffer);
            // The first element might be incomplete, keep in buffer
            $buffer = array_shift($chunkLines);

            for ($i = count($chunkLines) - 1; $i >= 0; $i--) {
                array_unshift($lines, $chunkLines[$i]);
                if (count($lines) >= $maxLines) {
                    break;
                }
            }
        }

        if ($buffer !== '' && count($lines) < $maxLines) {
            array_unshift($lines, $buffer);
        }

        fclose($handle);

        return $lines;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' МБ';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' КБ';
        }
        return $bytes . ' Б';
    }
}
