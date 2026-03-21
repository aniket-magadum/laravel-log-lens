<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Lens</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            background: #0f172a;
            color: #94a3b8;
            min-height: 100vh;
        }

        header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        header h1 {
            font-size: 1.25rem;
            color: #f1f5f9;
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        header span { font-size: 0.75rem; color: #64748b; }

        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem 2rem; }

        /* Sticky controls */
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #0f172a;
        }

        /* Level badges strip */
        .level-strip {
            background: #131e2e;
            border-bottom: 1px solid #1e2d40;
            padding: 0.5rem 2rem;
        }

        .level-strip-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: nowrap;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .level-strip-inner::-webkit-scrollbar { display: none; }

        /* Filter strip */
        .filter-strip {
            background: #0f172a;
            border-bottom: 1px solid #334155;
            padding: 0.45rem 2rem;
        }

        .filter-strip-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Summary badges */
        .summary { display: contents; }  /* children flow directly into level-strip-inner */

        .badge {
            padding: 0.18rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            font-family: inherit;
            -webkit-appearance: none;
            appearance: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border: 1px solid transparent;
            white-space: nowrap;
            transition: opacity 0.15s;
        }

        .badge:hover { opacity: 0.8; }
        .badge.active { border-color: currentColor; }

        .badge-all    { background: #1e293b; color: #94a3b8; }
        .badge-debug  { background: #172554; color: #93c5fd; }
        .badge-info   { background: #052e16; color: #86efac; }
        .badge-notice { background: #1c1917; color: #a8a29e; }
        .badge-warning{ background: #431407; color: #fdba74; }
        .badge-error  { background: #450a0a; color: #fca5a5; }
        .badge-critical,
        .badge-alert,
        .badge-emergency { background: #4c0519; color: #f9a8d4; }

        /* Filters */
        .filters {
            display: contents;
        }

        .filters input[type=text] {
            background: #1a2540;
            border: 1px solid #334155;
            color: #cbd5e1;
            border-radius: 0.375rem;
            padding: 0.3rem 0.75rem;
            font-size: 0.8125rem;
            font-family: inherit;
            outline: none;
            flex: 1;
            min-width: 0;
        }

        .filters input[type=text]:focus { border-color: #6366f1; }

        /* File dropdown */
        .file-dropdown { position: relative; }

        .file-dropdown-btn {
            background: #1a2540;
            border: 1px solid #334155;
            color: #94a3b8;
            border-radius: 0.375rem;
            padding: 0.3rem 0.65rem;
            font-size: 0.8125rem;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
            outline: none;
        }

        .file-dropdown-btn:focus,
        .file-dropdown-btn:hover { border-color: #6366f1; }

        .file-dropdown-btn.active { border-color: #6366f1; color: #a5b4fc; }

        .file-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.5rem;
            padding: 0.375rem 0;
            min-width: 160px;
            z-index: 200;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }

        .file-dropdown-menu.open { display: block; }

        .file-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.875rem;
            font-size: 0.8125rem;
            color: #cbd5e1;
            cursor: pointer;
        }

        .file-option:hover { background: #263348; }

        .file-option input[type=checkbox] { accent-color: #6366f1; cursor: pointer; }

        .btn {
            padding: 0.375rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-family: inherit;
            cursor: pointer;
            border: none;
            font-weight: 500;
            transition: background 0.15s;
        }

        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #334155; color: #94a3b8; }
        .btn-secondary:hover { background: #475569; }

        /* Table */
        .table-wrapper {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.5rem;
            overflow: clip;          /* clips visually but never traps scroll */
            -webkit-overflow-scrolling: touch;
        }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }

        thead tr { background: #0f172a; }

        th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            font-weight: 600;
            border-bottom: 1px solid #334155;
            overflow: hidden;
        }

        th.col-datetime  { width: 13%; }
        th.col-level     { width: 9%; }
        th.col-message   { /* takes the remaining ~67% */ }
        th.col-file      { width: 11%; }

        td {
            padding: 0.625rem 1rem;
            font-size: 0.8125rem;
            border-bottom: 1px solid #1e293b;
            vertical-align: top;
        }

        tbody tr { background: #1e293b; transition: background 0.1s; }
        tbody tr:hover { background: #263348; }
        tbody tr:last-child td { border-bottom: none; }

        .level-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
        }

        .level-debug    { background: #172554; color: #93c5fd; }
        .level-info     { background: #052e16; color: #86efac; }
        .level-notice   { background: #1c1917; color: #a8a29e; }
        .level-warning  { background: #431407; color: #fdba74; }
        .level-error    { background: #450a0a; color: #fca5a5; }
        .level-critical,
        .level-alert,
        .level-emergency { background: #4c0519; color: #f9a8d4; }

        .datetime { color: #64748b; white-space: nowrap; }
        .file-col { color: #64748b; font-size: 0.75rem; white-space: nowrap; }

        /* Collapsible rows */
        .log-summary {
            cursor: pointer;
            user-select: none;
            touch-action: manipulation;  /* prevents iOS 300ms delay / scroll trap */
        }

        .message-preview {
            color: #cbd5e1;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            display: inline-block;
        }

        .log-detail { display: none; background: #0a1020 !important; }
        .log-detail.open { display: table-row; }
        .log-detail td {
            padding: 0.75rem 1.25rem 0.75rem 3rem;
            border-bottom: 1px solid #334155;
        }
        .log-detail pre {
            white-space: pre-wrap;
            word-break: break-word;
            color: #94a3b8;
            font-family: inherit;
            font-size: 0.8rem;
            line-height: 1.65;
            margin: 0;
        }
        .log-detail .detail-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
            color: #64748b;
        }
        .log-detail .detail-meta span { color: #94a3b8; }

        /* Empty state */
        .empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #475569;
        }

        .empty p { font-size: 1rem; }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.25rem;
            font-size: 0.8125rem;
        }

        .pagination-info { color: #64748b; }

        .pagination-links { display: flex; gap: 0.5rem; }

        .pagination-links a,
        .pagination-links span {
            padding: 0.3rem 0.7rem;
            border-radius: 0.375rem;
            background: #1e293b;
            border: 1px solid #334155;
            color: #94a3b8;
            text-decoration: none;
            transition: background 0.15s;
        }

        .pagination-links a:hover  { background: #334155; }
        .pagination-links span.current { background: #6366f1; border-color: #6366f1; color: #fff; }
        .pagination-links span.disabled { opacity: 0.4; cursor: not-allowed; }

        /* ── Mobile responsive ── */
        @media (max-width: 640px) {
            header { padding: 0.625rem 1rem; }
            header h1 { font-size: 1rem; }

            .level-strip  { padding: 0.4rem 1rem; }
            .filter-strip { padding: 0.5rem 1rem; }

            .filter-strip-inner { flex-wrap: wrap; gap: 0.4rem; }
            .file-dropdown { flex: 0 0 auto; }
            .file-dropdown-btn { white-space: nowrap; }
            .filters input[type=text] { flex: 1 1 0; min-width: 120px; }
            .filter-actions { display: flex; gap: 0.4rem; width: 100%; }
            .filter-actions .btn { flex: 1; text-align: center; }

            .container { padding: 0.75rem; }

            /* ── Card layout: ditch table columns, stack rows as cards ── */
            .table-wrapper {
                background: transparent;
                border: none;
                border-radius: 0;
                overflow: visible;
            }

            table, tbody { display: block; width: 100%; }
            thead { display: none; }

            /* Each summary row → flex card */
            tr.log-summary {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                background: #1e293b;
                border: 1px solid #334155;
                border-radius: 0.375rem;
                margin-bottom: 0.25rem;
                padding: 0.6rem 0.75rem;
            }

            tr.log-summary td {
                display: block;
                padding: 0;
                border: none;
                background: transparent;
            }

            tr.log-summary td.datetime,
            tr.log-summary td.file-col { display: none; }

            /* Level badge cell */
            tr.log-summary td:nth-child(2) { flex: 0 0 auto; }

            /* Message cell fills the rest */
            tr.log-summary td:nth-child(3) {
                flex: 1 1 0;
                min-width: 0;
                overflow: hidden;
            }

            /* Detail row opens as a full-width block under the card */
            tr.log-detail.open {
                display: block;
                margin-top: -0.3rem;
                margin-bottom: 0.25rem;
                border: 1px solid #334155;
                border-top: none;
                border-radius: 0 0 0.375rem 0.375rem;
                background: #0a1020;
            }

            tr.log-detail td {
                display: block;
                padding: 0.625rem 0.75rem;
            }

            .log-detail .detail-meta { flex-direction: column; gap: 0.25rem; }

            .pagination { flex-direction: column; align-items: flex-start; gap: 0.625rem; }
            .pagination-links { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<div class="sticky-top">
    <header>
        <h1>&#128269; Log Lens</h1>
        <span>{{ $totalLogs }} entr{{ $totalLogs === 1 ? 'y' : 'ies' }} found</span>
    </header>

    {{-- Level badges strip --}}
    <div class="level-strip">
        <div class="level-strip-inner">
            @php $levels = ['all', 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']; @endphp
            @foreach ($levels as $lvl)
                @php
                    $count = $lvl === 'all' ? array_sum($summary) : ($summary[$lvl] ?? 0);
                    $isActive = $lvl === 'all' ? empty($selectedLevels) : in_array($lvl, $selectedLevels);
                @endphp
                @if ($lvl === 'all' || $count > 0)
                    <button type="button"
                            class="badge badge-{{ $lvl }}{{ $isActive ? ' active' : '' }}"
                            data-level="{{ $lvl }}"
                            onclick="toggleLevel('{{ $lvl }}')">
                        {{ ucfirst($lvl) }} <span>{{ $count }}</span>
                    </button>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Filter strip --}}
    <div class="filter-strip">
        <div class="filter-strip-inner">
            <form id="filter-form" method="GET" action="{{ route('log-lens.index') }}" class="filters" style="display:contents">
                <input type="hidden" name="page" value="1">

                <div class="file-dropdown" id="file-dropdown">
                    <button type="button"
                            class="file-dropdown-btn{{ ! empty($selectedLogFiles) ? ' active' : '' }}"
                            onclick="toggleFileDropdown(event)">
                        @php
                            $fileLabel = empty($selectedLogFiles)
                                ? 'All Files'
                                : count($selectedLogFiles).' file'.(count($selectedLogFiles) > 1 ? 's' : '');
                        @endphp
                        <span id="file-label">{{ $fileLabel }}</span>
                        <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>
                    </button>
                    <div class="file-dropdown-menu" id="file-dropdown-menu">
                        @foreach ($logFiles as $lf)
                            <label class="file-option">
                                <input type="checkbox" name="log_file[]" value="{{ $lf }}" @checked(in_array($lf, $selectedLogFiles))>
                                {{ $lf }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <input type="text" name="search" placeholder="Search messages…" value="{{ $search }}">

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('log-lens.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="container">

    {{-- Log table --}}
    <div class="table-wrapper">
        @if ($pagedLogs->isEmpty())
            <div class="empty"><p>No log entries match your filters.</p></div>
        @else
            <table>
                <thead>
                    <tr>
                        <th class="col-datetime">Datetime</th>
                        <th class="col-level">Level</th>
                        <th class="col-message">Message</th>
                        <th class="col-file">File</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagedLogs as $log)
                        @php $idx = $loop->index; @endphp
                        <tr class="log-summary" onclick="toggleRow({{ $idx }})">
                            <td class="datetime">{{ $log['datetime'] }}</td>
                            <td>
                                <span class="level-badge level-{{ $log['level'] }}">{{ $log['level'] }}</span>
                            </td>
                            <td style="overflow:hidden">
                                <span class="message-preview">{{ $log['message'] }}</span>
                            </td>
                            <td class="file-col">{{ $log['file'] }}</td>
                        </tr>
                        <tr class="log-detail" id="log-detail-{{ $idx }}">
                            <td colspan="4">
                                <div class="detail-meta">
                                    <div>Environment: <span>{{ $log['environment'] }}</span></div>
                                    <div>File: <span>{{ $log['file'] }}</span></div>
                                    <div>Time: <span>{{ $log['datetime'] }}</span></div>
                                </div>
                                <pre>{{ $log['message'] }}</pre>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Pagination --}}
    @if ($lastPage > 1)
        <div class="pagination">
            <span class="pagination-info">
                Page {{ $currentPage }} of {{ $lastPage }}
                &nbsp;&middot;&nbsp;
                {{ $totalLogs }} total entries
            </span>
            <div class="pagination-links">
                @if ($currentPage > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}">&laquo; Prev</a>
                @else
                    <span class="disabled">&laquo; Prev</span>
                @endif

                @for ($p = max(1, $currentPage - 2); $p <= min($lastPage, $currentPage + 2); $p++)
                    @if ($p === $currentPage)
                        <span class="current">{{ $p }}</span>
                    @else
                        <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a>
                    @endif
                @endfor

                @if ($currentPage < $lastPage)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}">Next &raquo;</a>
                @else
                    <span class="disabled">Next &raquo;</span>
                @endif
            </div>
        </div>
    @endif

</div>

<script>
    let selectedLevels = @json($selectedLevels);

    function toggleFileDropdown(e) {
        e.stopPropagation();
        document.getElementById('file-dropdown-menu').classList.toggle('open');
    }

    document.addEventListener('click', function (e) {
        const dd = document.getElementById('file-dropdown');
        const menu = document.getElementById('file-dropdown-menu');
        if (menu && dd && !dd.contains(e.target)) {
            menu.classList.remove('open');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('filter-form');
        form.addEventListener('submit', function () {
            this.querySelectorAll('input[name="level[]"]').forEach(el => el.remove());
            selectedLevels.forEach(function (l) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'level[]';
                input.value = l;
                form.appendChild(input);
            });
        });
    });

    function toggleLevel(lvl) {
        if (lvl === 'all') {
            selectedLevels = [];
        } else if (selectedLevels.includes(lvl)) {
            selectedLevels = selectedLevels.filter(l => l !== lvl);
        } else {
            selectedLevels.push(lvl);
        }

        document.querySelectorAll('.badge[data-level]').forEach(function (badge) {
            const bl = badge.dataset.level;
            badge.classList.toggle('active', bl === 'all' ? selectedLevels.length === 0 : selectedLevels.includes(bl));
        });

        const form = document.getElementById('filter-form');
        form.querySelectorAll('input[name="level[]"]').forEach(el => el.remove());
        selectedLevels.forEach(function (l) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'level[]';
            input.value = l;
            form.appendChild(input);
        });
        form.submit();
    }

    function toggleRow(i) {
        const summary = document.querySelectorAll('.log-summary')[i];
        const detail  = document.getElementById('log-detail-' + i);
        if (!summary || !detail) return;
        const isOpen = summary.classList.toggle('open');
        detail.classList.toggle('open', isOpen);
    }
</script>
</body>
</html>
