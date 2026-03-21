<?php

namespace AniketMagadum\LogLens\Http\Controllers;

use AniketMagadum\LogLens\LogLens;
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
        $search = $request->query('search');
        $perPage = (int) config('log-lens.per_page', 50);

        $logs = $this->logLens->filter($selectedLevels, $search, $selectedLogFiles);
        $logFiles = $this->logLens->getLogFileNames();
        $summary = $this->logLens->summary($selectedLogFiles);

        $currentPage = (int) $request->query('page', 1);
        $totalLogs = $logs->count();
        $pagedLogs = $logs->forPage($currentPage, $perPage);
        $lastPage = (int) ceil($totalLogs / $perPage) ?: 1;

        return view('log-lens::index', compact(
            'pagedLogs',
            'logFiles',
            'selectedLogFiles',
            'summary',
            'selectedLevels',
            'search',
            'currentPage',
            'lastPage',
            'totalLogs',
            'perPage',
        ));
    }
}
