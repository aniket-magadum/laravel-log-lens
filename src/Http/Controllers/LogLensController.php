<?php

namespace AniketMagadum\LogLens\Http\Controllers;

use AniketMagadum\LogLens\LogLens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class LogLensController extends Controller
{
    public function __construct(protected LogLens $logLens) {}

    public function index(Request $request): View
    {
        $selectedLevels = array_values(array_filter((array) ($request->query('level') ?? [])));
        $selectedLogFiles = array_values(array_filter((array) ($request->query('log_file') ?? [])));

        // On the first visit (no explicit file filter submitted), pre-select the most relevant file
        if (! $request->boolean('_files_set') && empty($selectedLogFiles)) {
            $default = $this->guessDefaultLogFile();
            if ($default !== null) {
                $selectedLogFiles = [$default];
            }
        }

        $rawSearch = $request->query('search');
        $selectedSearches = array_values(array_filter(
            is_array($rawSearch) ? $rawSearch : (($rawSearch !== null && $rawSearch !== '') ? [$rawSearch] : [])
        ));

        $contextFilters = array_filter((array) ($request->query('ctx') ?? []));
        $resolveFilter = in_array($request->query('resolve_filter'), ['resolved', 'pending'])
            ? $request->query('resolve_filter')
            : 'all';

        $perPage = (int) config('log-lens.per_page', 50);

        $logs = $this->logLens->filter($selectedLevels, $selectedSearches, $selectedLogFiles, $contextFilters);
        $logFiles = $this->logLens->getLogFileNames();
        $summary = $this->logLens->summary($selectedLogFiles);
        $contextKeyValues = $this->logLens->getContextKeyValues($selectedLogFiles);

        $resolvedIds = $this->getResolvedIds();

        $highlightLogId = (string) ($request->query('log') ?? '');
        $highlightPos = null;
        if ($highlightLogId !== '' && preg_match('/^[a-f0-9]{32}$/', $highlightLogId)) {
            $allLogs = $this->logLens->filter([], [], [], []);
            foreach ($allLogs as $i => $log) {
                if (md5($log['datetime'].$log['level'].$log['message'].$log['file']) === $highlightLogId) {
                    $highlightPos = $i;
                    break;
                }
            }
            if ($highlightPos !== null) {
                $logs = $allLogs;
                $selectedLevels = [];
                $selectedSearches = [];
                $selectedLogFiles = [];
                $contextFilters = [];
                $resolveFilter = 'all';
            } else {
                $highlightLogId = '';
            }
        } else {
            $highlightLogId = '';
        }

        $resolvableLogs = $logs->whereNotIn('level', ['debug', 'info']);
        $allIds = $resolvableLogs->map(fn ($log) => md5($log['datetime'].$log['level'].$log['message'].$log['file']))->all();
        $resolvedCount = count(array_intersect($resolvedIds, $allIds));
        $pendingCount = count($allIds) - $resolvedCount;

        if ($resolveFilter === 'resolved') {
            $logs = $logs->filter(fn ($log) => in_array($log['level'], ['debug', 'info'])
                ? false
                : in_array(md5($log['datetime'].$log['level'].$log['message'].$log['file']), $resolvedIds, true)
            )->values();
        } elseif ($resolveFilter === 'pending') {
            $logs = $logs->filter(fn ($log) => in_array($log['level'], ['debug', 'info'])
                ? true
                : ! in_array(md5($log['datetime'].$log['level'].$log['message'].$log['file']), $resolvedIds, true)
            )->values();
        }

        $currentPage = $highlightPos !== null
            ? (int) floor($highlightPos / $perPage) + 1
            : (int) $request->query('page', '1');
        $totalLogs = $logs->count();
        $pagedLogs = $logs->forPage($currentPage, $perPage);
        $lastPage = (int) ceil($totalLogs / $perPage) ?: 1;

        return view('log-lens::index', compact(
            'pagedLogs',
            'logFiles',
            'selectedLogFiles',
            'summary',
            'selectedLevels',
            'selectedSearches',
            'contextFilters',
            'contextKeyValues',
            'currentPage',
            'lastPage',
            'totalLogs',
            'perPage',
            'resolvedIds',
            'resolvedCount',
            'pendingCount',
            'resolveFilter',
            'highlightLogId',
        ));
    }

    public function toggleResolved(Request $request): JsonResponse
    {
        $id = (string) ($request->input('id') ?? '');

        if (! preg_match('/^[a-f0-9]{32}$/', $id)) {
            return response()->json(['error' => 'Invalid id'], 422);
        }

        $resolved = $this->getResolvedIds();

        if (in_array($id, $resolved, true)) {
            $resolved = array_values(array_diff($resolved, [$id]));
            $isResolved = false;
        } else {
            $resolved[] = $id;
            $isResolved = true;
        }

        $this->saveResolvedIds($resolved);

        return response()->json(['resolved' => $isResolved]);
    }

    public function resolveAllByMessage(Request $request): JsonResponse
    {
        $message = (string) ($request->input('message') ?? '');

        if ($message === '') {
            return response()->json(['error' => 'Message is required'], 422);
        }

        $allLogs = $this->logLens->filter([], [], [], []);
        $resolvedIds = $this->getResolvedIds();
        $newCount = 0;

        foreach ($allLogs as $log) {
            if (in_array($log['level'], ['debug', 'info'])) {
                continue;
            }

            if ($log['message'] !== $message) {
                continue;
            }

            $id = md5($log['datetime'].$log['level'].$log['message'].$log['file']);

            if (! in_array($id, $resolvedIds, true)) {
                $resolvedIds[] = $id;
                $newCount++;
            }
        }

        $this->saveResolvedIds($resolvedIds);

        return response()->json(['resolved' => $newCount]);
    }

    /**
     * Return the most relevant log file name to show by default.
     * Priority: today's dated file → yesterday's → "laravel.log" → null (all files).
     */
    private function guessDefaultLogFile(): ?string
    {
        $files = $this->logLens->getLogFileNames();

        if (empty($files)) {
            return null;
        }

        $today     = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        foreach ([$today, $yesterday] as $date) {
            foreach ($files as $file) {
                if (str_contains($file, $date)) {
                    return $file;
                }
            }
        }

        if (in_array('laravel.log', $files, true)) {
            return 'laravel.log';
        }

        // Final resort: the file with the most recent modification time
        $fullPaths = $this->logLens->getLogFiles();
        usort($fullPaths, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return basename($fullPaths[0]);
    }

    private function resolvedStoragePath(): string
    {
        return storage_path('app/log-lens-resolved.json');
    }

    /** @return array<int, string> */
    private function getResolvedIds(): array
    {
        $path = $this->resolvedStoragePath();
        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<int, string> $ids */
    private function saveResolvedIds(array $ids): void
    {
        file_put_contents($this->resolvedStoragePath(), json_encode(array_values($ids)));
    }
}
