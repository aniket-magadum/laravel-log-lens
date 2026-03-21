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
            flex-wrap: wrap;
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

        /* Search chip input */
        .search-chip-wrapper {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.3rem;
            background: #1a2540;
            border: 1px solid #334155;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            flex: 1;
            min-width: 0;
            cursor: text;
            transition: border-color 0.15s;
        }

        .search-chip-wrapper:focus-within { border-color: #6366f1; }

        .search-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #312e81;
            border: 1px solid #4338ca;
            color: #c7d2fe;
            border-radius: 9999px;
            padding: 0.1rem 0.5rem;
            font-size: 0.75rem;
            white-space: nowrap;
            max-width: 200px;
        }

        .search-chip span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .chip-remove {
            background: none;
            border: none;
            color: #a5b4fc;
            cursor: pointer;
            font-size: 0.85rem;
            line-height: 1;
            padding: 0;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .chip-remove:hover { color: #e0e7ff; }

        .search-chip-input {
            background: transparent;
            border: none;
            outline: none;
            color: #cbd5e1;
            font-size: 0.8125rem;
            font-family: inherit;
            min-width: 120px;
            flex: 1;
            padding: 0.05rem 0.25rem;
        }

        /* Context filter chips */
        .ctx-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #0d2922;
            border: 1px solid #134e3e;
            color: #5eead4;
            border-radius: 9999px;
            padding: 0.1rem 0.4rem 0.1rem 0.5rem;
            font-size: 0.75rem;
            white-space: nowrap;
            max-width: 240px;
        }

        .ctx-filter-chip > span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ctx-filter-chip .chip-remove {
            background: none;
            border: none;
            color: #5eead4;
            cursor: pointer;
            font-size: 0.85rem;
            line-height: 1;
            padding: 0;
            opacity: 0.65;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .ctx-filter-chip .chip-remove:hover { opacity: 1; }

        /* Context filter row */
        .ctx-filter-row {
            flex: 0 0 100%;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
            padding-top: 0.1rem;
        }

        .ctx-filter-label {
            font-size: 0.7rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .ctx-combobox {
            position: relative;
            max-width: 160px;
        }

        .ctx-combo-input {
            background: #1a2540;
            border: 1px solid #334155;
            color: #cbd5e1;
            border-radius: 0.375rem;
            padding: 0.28rem 0.55rem;
            font-size: 0.8125rem;
            font-family: inherit;
            outline: none;
            width: 100%;
        }

        .ctx-combo-input:focus { border-color: #6366f1; }

        .ctx-combo-list {
            display: none;
            position: absolute;
            top: calc(100% + 3px);
            left: 0;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.375rem;
            max-height: 180px;
            overflow-y: auto;
            z-index: 300;
            min-width: 100%;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
        }

        .ctx-combo-list.open { display: block; }

        .ctx-combo-option {
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            color: #cbd5e1;
            cursor: pointer;
            white-space: nowrap;
        }

        .ctx-combo-option:hover,
        .ctx-combo-option.highlighted { background: #263348; }

        .ctx-combo-empty {
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            color: #475569;
            font-style: italic;
        }

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
            padding: 0.75rem 1.25rem 0.75rem 1.25rem;
            border-bottom: 1px solid #334155;
        }

        /* ── Detail: meta strip ── */
        .log-detail .detail-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 0.75rem;
            font-size: 0.75rem;
            color: #64748b;
        }
        .log-detail .detail-meta span { color: #94a3b8; }

        /* ── Detail: message body ── */
        .detail-message {
            white-space: pre-wrap;
            word-break: break-word;
            color: #cbd5e1;
            font-family: inherit;
            font-size: 0.8rem;
            line-height: 1.65;
            margin: 0 0 0.75rem;
            padding: 0.625rem 0.875rem;
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 0.375rem;
        }

        /* ── Detail: context panel ── */
        .context-panel {
            margin-bottom: 0.625rem;
        }

        .context-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            font-weight: 600;
            margin-bottom: 0.375rem;
        }

        .context-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }

        .context-pill {
            display: inline-flex;
            align-items: stretch;
            border: 1px solid #1e3050;
            border-radius: 0.3rem;
            font-size: 0.73rem;
            overflow: hidden;
            cursor: pointer;
            transition: border-color 0.15s;
            max-width: 360px;
        }

        .context-pill:hover { border-color: #4f6080; }
        .context-pill:hover .context-pill-key { background: #1e2d40; }

        .context-pill-key {
            background: #0f172a;
            color: #64748b;
            padding: 0.2rem 0.45rem;
            border-right: 1px solid #1e3050;
            white-space: nowrap;
            transition: background 0.15s;
        }

        .context-pill-val {
            color: #94a3b8;
            padding: 0.2rem 0.45rem;
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Detail: exception block ── */
        .exception-block {
            margin-top: 0.5rem;
        }

        .exception-block pre {
            white-space: pre-wrap;
            word-break: break-word;
            color: #fca5a5;
            font-family: inherit;
            font-size: 0.75rem;
            line-height: 1.65;
            margin: 0;
            padding: 0.625rem 0.875rem;
            background: #1a0808;
            border: 1px solid #450a0a;
            border-radius: 0.375rem;
        }

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
            .ctx-combobox { flex: 1; min-width: 100px; max-width: none; }
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

            .context-pill-val { max-width: 160px; }
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

                <div class="search-chip-wrapper" id="searchChipWrapper" onclick="document.getElementById('searchTextInput').focus()">
                    @foreach ($selectedSearches as $term)
                        <span class="search-chip">
                            <span>{{ $term }}</span>
                            <input type="hidden" name="search[]" value="{{ $term }}">
                            <button type="button" class="chip-remove" onclick="removeSearchChip(event, this)" title="Remove">&#x2715;</button>
                        </span>
                    @endforeach
                    <input type="text"
                           id="searchTextInput"
                           class="search-chip-input"
                           placeholder="{{ empty($selectedSearches) ? 'Search… (Enter to add)' : 'Add another…' }}"
                           autocomplete="off">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('log-lens.index') }}" class="btn btn-secondary">Reset</a>
                </div>

                {{-- Context filter row --}}
                @if (!empty($contextKeyValues) || !empty($contextFilters))
                    <div class="ctx-filter-row">
                        @foreach ($contextFilters as $ctxKey => $ctxVal)
                            <span class="ctx-filter-chip">
                                <input type="hidden" name="ctx[{{ $ctxKey }}]" value="{{ $ctxVal }}">
                                <span title="{{ $ctxKey }}: {{ $ctxVal }}">{{ $ctxKey }}: {{ $ctxVal }}</span>
                                <button type="button" class="chip-remove" onclick="removeCtxFilter(event, {{ json_encode($ctxKey) }})" title="Remove">&#x2715;</button>
                            </span>
                        @endforeach

                        @if (!empty($contextKeyValues))
                            <span class="ctx-filter-label">ctx:</span>
                            <div class="ctx-combobox" id="ctxKeyCombo">
                                <input type="text" id="ctxKeyInput" class="ctx-combo-input"
                                       placeholder="key&hellip;" autocomplete="off"
                                       oninput="filterCtxCombo('key', this.value)"
                                       onfocus="openCtxCombo('key')"
                                       onkeydown="ctxComboKeydown(event, 'key')">
                                <div class="ctx-combo-list" id="ctxKeyList"></div>
                            </div>
                            <div class="ctx-combobox" id="ctxValCombo">
                                <input type="text" id="ctxValInput" class="ctx-combo-input"
                                       placeholder="value&hellip;" autocomplete="off"
                                       oninput="filterCtxCombo('val', this.value)"
                                       onfocus="openCtxCombo('val')"
                                       onkeydown="ctxComboKeydown(event, 'val')">
                                <div class="ctx-combo-list" id="ctxValList"></div>
                            </div>
                            <button type="button" class="btn btn-secondary" style="padding: 0.28rem 0.65rem; font-size: 0.8125rem;" onclick="addCtxFilter()">Add</button>
                        @endif
                    </div>
                @endif
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
                                {{-- Meta strip --}}
                                <div class="detail-meta">
                                    <div>Env: <span>{{ $log['environment'] }}</span></div>
                                    <div>File: <span>{{ $log['file'] }}</span></div>
                                    <div>Time: <span>{{ $log['datetime'] }}</span></div>
                                </div>

                                {{-- Message body --}}
                                <pre class="detail-message">{{ $log['message'] }}</pre>

                                @php
                                    $ctxDisplay = collect($log['context'] ?? [])->except('exception');
                                    $hasException = ! empty($log['context']['exception']);
                                @endphp

                                {{-- Context key-value pills --}}
                                @if ($ctxDisplay->isNotEmpty())
                                    <div class="context-panel">
                                        <div class="context-label">Context</div>
                                        <div class="context-grid">
                                            @foreach ($ctxDisplay as $key => $val)
                                                @php
                                                    $display = is_array($val) ? json_encode($val, JSON_UNESCAPED_SLASHES) : (string) $val;
                                                @endphp
                                                <div class="context-pill"
                                                     title="{{ is_array($val) ? 'Click to search for this value' : 'Click to filter by '.$key }}"
                                                     onclick="{{ is_array($val) ? 'searchContext('.json_encode($display).')' : 'filterByCtx('.json_encode($key).', '.json_encode($display).')' }}">
                                                    <span class="context-pill-key">{{ $key }}</span>
                                                    <span class="context-pill-val" title="{{ $display }}">{{ $display }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Exception block --}}
                                @if ($hasException)
                                    <div class="exception-block">
                                        <div class="context-label">Exception</div>
                                        <pre>{{ $log['context']['exception'] }}</pre>
                                    </div>
                                @endif
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
        ['ctxKeyCombo', 'ctxValCombo'].forEach(function (comboId) {
            const combo = document.getElementById(comboId);
            const list  = document.getElementById(comboId === 'ctxKeyCombo' ? 'ctxKeyList' : 'ctxValList');
            if (combo && list && !combo.contains(e.target)) {
                list.classList.remove('open');
            }
        });
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

    function addSearchChip(value) {
        const term = value.trim();
        if (term === '') { return; }

        // Prevent duplicate chips
        const existing = [...document.querySelectorAll('#searchChipWrapper input[name="search[]"]')]
            .map(el => el.value.toLowerCase());
        if (existing.includes(term.toLowerCase())) { return; }

        const form = document.getElementById('filter-form');
        const wrapper = document.getElementById('searchChipWrapper');
        const textInput = document.getElementById('searchTextInput');

        const chip = document.createElement('span');
        chip.className = 'search-chip';
        chip.innerHTML =
            '<span>' + term.replace(/</g, '&lt;') + '</span>' +
            '<input type="hidden" name="search[]" value="' + term.replace(/"/g, '&quot;') + '">' +
            '<button type="button" class="chip-remove" onclick="removeSearchChip(event, this)" title="Remove">&#x2715;</button>';

        wrapper.insertBefore(chip, textInput);
        textInput.value = '';
        textInput.placeholder = 'Add another…';

        const pageInput = form.querySelector('input[name="page"]');
        if (pageInput) { pageInput.value = '1'; }
        form.submit();
    }

    function removeSearchChip(event, btn) {
        event.stopPropagation();
        const form = document.getElementById('filter-form');
        btn.closest('.search-chip').remove();
        const pageInput = form.querySelector('input[name="page"]');
        if (pageInput) { pageInput.value = '1'; }
        form.submit();
    }

    document.getElementById('searchTextInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSearchChip(this.value);
        } else if (e.key === 'Backspace' && this.value === '') {
            // Remove last chip on backspace when input is empty
            const chips = document.querySelectorAll('#searchChipWrapper .search-chip');
            if (chips.length > 0) {
                removeSearchChip(e, chips[chips.length - 1].querySelector('.chip-remove'));
            }
        }
    });

    function searchContext(value) {
        addSearchChip(value);
    }

    const ctxKeyValues = @json($contextKeyValues);
    let ctxSelectedKey  = null;
    let ctxSelectedVal  = null;
    let ctxValOptions   = [];
    let ctxKeyHighlight = -1;
    let ctxValHighlight = -1;

    function openCtxCombo(type) {
        const listId = type === 'key' ? 'ctxKeyList' : 'ctxValList';
        const inputId = type === 'key' ? 'ctxKeyInput' : 'ctxValInput';
        const input = document.getElementById(inputId);
        if (type === 'key') { ctxKeyHighlight = -1; } else { ctxValHighlight = -1; }
        populateCtxComboList(type, input ? input.value : '');
        document.getElementById(listId).classList.add('open');
    }

    function populateCtxComboList(type, filter) {
        const listId = type === 'key' ? 'ctxKeyList' : 'ctxValList';
        const list = document.getElementById(listId);
        if (!list) { return; }
        list.innerHTML = '';
        const options = type === 'key' ? Object.keys(ctxKeyValues) : ctxValOptions;
        const q = (filter || '').toLowerCase();
        const filtered = q ? options.filter(function (o) { return String(o).toLowerCase().includes(q); }) : options;
        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'ctx-combo-empty';
            empty.textContent = 'No matches';
            list.appendChild(empty);
            return;
        }
        filtered.forEach(function (val) {
            const opt = document.createElement('div');
            opt.className = 'ctx-combo-option';
            opt.textContent = String(val);
            opt.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectCtxOption(type, String(val));
            });
            list.appendChild(opt);
        });
    }

    function filterCtxCombo(type, query) {
        if (type === 'key') { ctxKeyHighlight = -1; } else { ctxValHighlight = -1; }
        populateCtxComboList(type, query);
        document.getElementById(type === 'key' ? 'ctxKeyList' : 'ctxValList').classList.add('open');
    }

    function selectCtxOption(type, value) {
        if (type === 'key') {
            ctxSelectedKey = value;
            document.getElementById('ctxKeyInput').value = value;
            document.getElementById('ctxKeyList').classList.remove('open');
            ctxSelectedVal = null;
            const valInput = document.getElementById('ctxValInput');
            if (valInput) { valInput.value = ''; valInput.focus(); }
            ctxValOptions = (ctxKeyValues[value] || []).map(String);
            populateCtxComboList('val', '');
            document.getElementById('ctxValList').classList.add('open');
        } else {
            ctxSelectedVal = value;
            document.getElementById('ctxValInput').value = value;
            document.getElementById('ctxValList').classList.remove('open');
        }
    }

    function ctxComboKeydown(event, type) {
        const listId = type === 'key' ? 'ctxKeyList' : 'ctxValList';
        const list = document.getElementById(listId);
        const options = list ? list.querySelectorAll('.ctx-combo-option') : [];
        let highlight = type === 'key' ? ctxKeyHighlight : ctxValHighlight;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight = Math.min(highlight + 1, options.length - 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight = Math.max(highlight - 1, -1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (highlight >= 0 && options[highlight]) {
                selectCtxOption(type, options[highlight].textContent);
            } else if (type === 'val') {
                addCtxFilter();
            }
            return;
        } else if (event.key === 'Escape') {
            if (list) { list.classList.remove('open'); }
            return;
        } else {
            return;
        }
        if (type === 'key') { ctxKeyHighlight = highlight; } else { ctxValHighlight = highlight; }
        options.forEach(function (o, i) { o.classList.toggle('highlighted', i === highlight); });
        if (highlight >= 0 && options[highlight]) { options[highlight].scrollIntoView({ block: 'nearest' }); }
    }

    function addCtxFilter() {
        const key = ctxSelectedKey || (document.getElementById('ctxKeyInput') || {}).value || '';
        const val = ctxSelectedVal || (document.getElementById('ctxValInput') || {}).value || '';
        if (!key.trim() || !val.trim()) { return; }
        filterByCtx(key.trim(), val.trim());
    }

    function filterByCtx(key, value) {
        const form = document.getElementById('filter-form');
        form.querySelectorAll('input[name="ctx[' + key + ']"]').forEach(function (el) { el.remove(); });
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'ctx[' + key + ']';
        input.value = value;
        form.appendChild(input);
        const pageInput = form.querySelector('input[name="page"]');
        if (pageInput) { pageInput.value = '1'; }
        form.submit();
    }

    function removeCtxFilter(event, key) {
        event.stopPropagation();
        const form = document.getElementById('filter-form');
        const chip = event.target.closest('.ctx-filter-chip');
        if (chip) { chip.remove(); }
        form.querySelectorAll('input[name="ctx[' + key + ']"]').forEach(function (el) { el.remove(); });
        const pageInput = form.querySelector('input[name="page"]');
        if (pageInput) { pageInput.value = '1'; }
        form.submit();
    }
</script>
</body>
</html>
