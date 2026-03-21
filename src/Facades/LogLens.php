<?php

namespace AniketMagadum\LogLens\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection getLogs()
 * @method static array getLogFiles()
 * @method static array getLogFileNames()
 * @method static Collection parseLogFile(string $filePath)
 * @method static Collection filter(array $levels = [], ?string $search = null, array $logFiles = [])
 * @method static array summary(array $logFiles = [])
 *
 * @see \AniketMagadum\LogLens\LogLens
 */
class LogLens extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'log-lens';
    }
}
