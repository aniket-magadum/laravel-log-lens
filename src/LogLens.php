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

        return collect($matches)->map(fn (array $match) => [
            'datetime' => $match[1],
            'environment' => $match[2],
            'level' => strtolower($match[3]),
            'message' => trim($match[4]),
            'file' => basename($filePath),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function filter(array $levels = [], ?string $search = null, array $logFiles = []): Collection
    {
        $logs = $this->getLogs();

        if (! empty($logFiles)) {
            $logs = $logs->whereIn('file', $logFiles);
        }

        if (! empty($levels)) {
            $logs = $logs->whereIn('level', $levels);
        }

        if ($search !== null && $search !== '') {
            $logs = $logs->filter(
                fn (array $log) => str_contains(strtolower($log['message']), strtolower($search))
            );
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
