<?php

namespace AniketMagadum\LogLens\Http\Controllers;

use AniketMagadum\LogLens\LogLens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class LogLensController extends Controller
{
    public function __construct(protected LogLens $logLens) {}

    public function index(Request $request): View
    {
        $selectedLevels = array_values(array_filter((array) ($request->query('level') ?? [])));
        $selectedLogFiles = array_values(array_filter((array) ($request->query('log_file') ?? [])));

        $rawSearch = $request->query('search');
        $selectedSearches = array_values(array_filter(
            is_array($rawSearch) ? $rawSearch : (($rawSearch !== null && $rawSearch !== '') ? [$rawSearch] : [])
        ));

        $contextFilters = array_filter((array) ($request->query('ctx') ?? []));

        $perPage = (int) config('log-lens.per_page', 50);

        $logs = $this->logLens->filter($selectedLevels, $selectedSearches, $selectedLogFiles, $contextFilters);
        $logFiles = $this->logLens->getLogFileNames();
        $summary = $this->logLens->summary($selectedLogFiles);
        $contextKeyValues = $this->logLens->getContextKeyValues($selectedLogFiles);

        $currentPage = (int) $request->query('page', 1);
        $totalLogs = $logs->count();
        $pagedLogs = $logs->forPage($currentPage, $perPage);
        $lastPage = (int) ceil($totalLogs / $perPage) ?: 1;

        $resolvedIds = $this->getResolvedIds();
        $resolvableLogs = $logs->whereNotIn('level', ['debug', 'info']);
        $allIds = $resolvableLogs->map(fn ($log) => md5($log['datetime'].$log['level'].$log['message'].$log['file']))->all();
        $resolvedCount = count(array_intersect($resolvedIds, $allIds));
        $pendingCount = count($allIds) - $resolvedCount;

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
