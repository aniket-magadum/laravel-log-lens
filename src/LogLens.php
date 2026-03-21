<?php

namespace AniketMagadum\LogLens;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class LogLens
{
    /** @param array<string, mixed> $config */
    public function __construct(protected array $config) {}

    /** @return Collection<int, array<string, mixed>> */
    public function getLogs(): Collection
    {
        $files = $this->getLogFiles();

        return collect($files)->flatMap(fn (string $file) => $this->parseLogFile($file));
    }

    /** @return array<int, string> */
    public function getLogFiles(): array
    {
        $path = $this->config['storage_path'] ?? storage_path('logs');

        return File::glob("{$path}/*.log") ?: [];
    }

    /** @return array<int, string> */
    public function getLogFileNames(): array
    {
        return collect($this->getLogFiles())
            ->map(fn (string $file) => basename($file))
            ->sort()
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function parseLogFile(string $filePath): Collection
    {
        if (! File::exists($filePath)) {
            return collect();
        }

        $contents = File::get($filePath);
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2}|Z)?)\] (\w+)\.(\w+): (.*?)(?=\[\d{4}-\d{2}-\d{2}|\z)/s';
        preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER);

        return collect($matches)->map(function (array $match) use ($filePath): array {
            $raw = trim($match[4]);
            $context = [];
            $cleanMessage = $raw;

            if (! str_contains($raw, "\n")) {
                // ── Single-line entry: "message {context_json}" ──
                if (preg_match('/^(.*?)\s+(\{.+\}|\[\])\s*$/', $raw, $ctx)) {
                    $cleanMessage = trim($ctx[1]);
                    if ($ctx[2] !== '[]') {
                        $context = json_decode($ctx[2], true) ?? [];
                    }
                }
            } else {
                // ── Multi-line entry (exception stacktrace spans lines) ──
                // Format: MESSAGE {"exception":"...\nmultiline\n"} {"extra":"context"}
                $lines = explode("\n", $raw);
                $firstLine = $lines[0];
                $lastLine = rtrim((string) end($lines));

                // Extract the exception string: {"exception":"...multiline..."}
                // Actual newlines appear literally inside the JSON string value (Monolog quirk)
                if (preg_match('/\{"exception":"((?:[^"\\\\]|\\\\.)*)"\}/s', $raw, $excMatch)) {
                    $context['exception'] = $excMatch[1];

                    // Clean message is the first-line text before the opening {
                    if (preg_match('/^(.*?)\s+\{/', $firstLine, $msgMatch)) {
                        $cleanMessage = trim($msgMatch[1]);
                    } else {
                        $cleanMessage = $firstLine;
                    }

                    // Additional context may appear after the closing "} on the last line
                    // e.g.  "} {"user_id":123}
                    if (preg_match('/^"\}\s*(\{.+\})\s*$/', $lastLine, $lcm)) {
                        $extra = json_decode($lcm[1], true);
                        if (is_array($extra)) {
                            $context = array_merge($context, $extra);
                        }
                    }
                } else {
                    // No exception pattern — fall back to first-line single-line parsing
                    if (preg_match('/^(.*?)\s+(\{.+\}|\[\])\s*$/', $firstLine, $ctx)) {
                        $cleanMessage = trim($ctx[1]);
                        if ($ctx[2] !== '[]') {
                            $context = json_decode($ctx[2], true) ?? [];
                        }
                    }
                }
            }

            return [
                'datetime' => $match[1],
                'environment' => $match[2],
                'level' => strtolower($match[3]),
                'message' => $cleanMessage,
                'context' => $context,
                'file' => basename($filePath),
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function filter(array $levels = [], array $searches = [], array $logFiles = []): Collection
    {
        $logs = $this->getLogs();

        if (! empty($logFiles)) {
            $logs = $logs->whereIn('file', $logFiles);
        }

        if (! empty($levels)) {
            $logs = $logs->whereIn('level', $levels);
        }

        if (! empty($searches)) {
            $logs = $logs->filter(function (array $log) use ($searches): bool {
                foreach ($searches as $term) {
                    $needle = strtolower((string) $term);
                    if ($needle === '') {
                        continue;
                    }

                    $matched = str_contains(strtolower($log['message']), $needle);

                    if (! $matched) {
                        foreach ($log['context'] as $value) {
                            if (is_string($value) && str_contains(strtolower($value), $needle)) {
                                $matched = true;
                                break;
                            }
                        }
                    }

                    if (! $matched) {
                        return false; // All terms must match (AND logic)
                    }
                }

                return true;
            });
        }

        return $logs->sortByDesc('datetime')->values();
    }

    /** @return array<string, int> */
    public function summary(array $logFiles = []): array
    {
        $logs = $this->getLogs();

        if (! empty($logFiles)) {
            $logs = $logs->whereIn('file', $logFiles);
        }

        return $logs
            ->groupBy('level')
            ->map(fn (Collection $group) => $group->count())
            ->toArray();
    }
}
