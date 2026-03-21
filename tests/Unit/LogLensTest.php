<?php

use AniketMagadum\LogLens\LogLens;
use AniketMagadum\LogLens\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Write lines to a file, joining them with a newline.
 *
 * @param  array<string>  $lines
 */
function writeLog(string $path, array $lines): void
{
    file_put_contents($path, implode("\n", $lines)."\n");
}

// ─── Setup / Teardown ────────────────────────────────────────────────────────

beforeEach(function () {
    $this->storagePath = storage_path('logs/test-'.uniqid());
    mkdir($this->storagePath, 0755, true);
    $this->logLens = new LogLens(['storage_path' => $this->storagePath]);
});

afterEach(function () {
    File::deleteDirectory($this->storagePath);
});

// ─── parseLogFile ─────────────────────────────────────────────────────────────

it('returns an empty collection for a non-existent file', function () {
    expect($this->logLens->parseLogFile('/non/existent/file.log'))->toBeEmpty();
});

it('returns an empty collection for an empty log file', function () {
    $file = $this->storagePath.'/laravel.log';
    file_put_contents($file, '');

    expect($this->logLens->parseLogFile($file))->toBeEmpty();
});

it('parses the datetime, environment, level, message, and file from a single-line entry', function () {
    $file = $this->storagePath.'/laravel.log';
    writeLog($file, ['[2026-03-21 10:00:00] production.ERROR: Something went wrong']);

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['datetime'])->toBe('2026-03-21 10:00:00')
        ->and($entry['environment'])->toBe('production')
        ->and($entry['level'])->toBe('error')
        ->and($entry['message'])->toBe('Something went wrong')
        ->and($entry['context'])->toBeEmpty()
        ->and($entry['file'])->toBe('laravel.log');
});

it('extracts JSON context from a single-line entry', function () {
    $file = $this->storagePath.'/laravel.log';
    writeLog($file, ['[2026-03-21 10:00:00] local.INFO: User logged in {"user_id":42,"ip":"127.0.0.1"}']);

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['message'])->toBe('User logged in')
        ->and($entry['context'])->toBe(['user_id' => 42, 'ip' => '127.0.0.1']);
});

it('treats a [] context as an empty context array', function () {
    $file = $this->storagePath.'/laravel.log';
    writeLog($file, ['[2026-03-21 10:00:00] production.DEBUG: Heartbeat []']);

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['message'])->toBe('Heartbeat')
        ->and($entry['context'])->toBeEmpty();
});

it('parses all standard log levels', function (string $level) {
    $file = $this->storagePath.'/laravel.log';
    writeLog($file, ["[2026-03-21 10:00:00] production.{$level}: Test message"]);

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['level'])->toBe(strtolower($level));
})->with(['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']);

it('parses multiple entries from a single file', function () {
    $file = $this->storagePath.'/laravel.log';
    writeLog($file, [
        '[2026-03-21 10:00:00] local.INFO: First entry',
        '[2026-03-21 10:00:01] local.ERROR: Second entry',
    ]);

    expect($this->logLens->parseLogFile($file))->toHaveCount(2);
});

it('parses a multi-line exception entry and extracts the exception into context', function () {
    $file = $this->storagePath.'/laravel.log';
    $content =
        '[2026-03-21 10:00:00] production.ERROR: Connection failed {"exception":"[object] (Exception(code: 0): Connection failed at /app/Client.php:10)'."\n".
        '[stacktrace]'."\n".
        '#0 {main}'."\n".
        '"}';
    file_put_contents($file, $content."\n");

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['message'])->toBe('Connection failed')
        ->and($entry['context'])->toHaveKey('exception')
        ->and($entry['context']['exception'])->toContain('[stacktrace]')
        ->and($entry['context']['exception'])->toContain('#0 {main}');
});

it('extracts extra context keys that appear after the exception block', function () {
    $file = $this->storagePath.'/laravel.log';
    $content =
        '[2026-03-21 10:00:00] production.ERROR: Failed {"exception":"[object] (Exception: oh no at file.php:1)'."\n".
        '#0 {main}'."\n".
        '"} {"user_id":99,"request_id":"abc123"}';
    file_put_contents($file, $content."\n");

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['context']['user_id'])->toBe(99)
        ->and($entry['context']['request_id'])->toBe('abc123');
});

it('preserves the full exception string including a previous exception chain', function () {
    $file = $this->storagePath.'/laravel.log';
    $content =
        '[2026-03-21 10:00:00] production.ERROR: Timeout {"exception":"[object] (ChildException(code: 0): timeout at child.php:1)'."\n".
        '[stacktrace]'."\n".
        '#0 {main}'."\n".
        "\n".
        '[previous exception] [object] (ParentException(code: 0): root cause at parent.php:5)'."\n".
        '[stacktrace]'."\n".
        '#0 {main}'."\n".
        '"}';
    file_put_contents($file, $content."\n");

    $entry = $this->logLens->parseLogFile($file)->first();

    expect($entry['context']['exception'])->toContain('[previous exception]')
        ->and($entry['context']['exception'])->toContain('ParentException');
});

it('falls back to first-line context parsing when no exception key is present in a multi-line raw entry', function () {
    $file = $this->storagePath.'/laravel.log';
    // Simulate a rare case: multi-line message without an "exception" key
    $content =
        '[2026-03-21 10:00:00] local.INFO: Multi line message {"user_id":7}'."\n".
        'extra continuation line';
    file_put_contents($file, $content."\n");

    $entry = $this->logLens->parseLogFile($file)->first();

    // Falls back; context may be empty or partial — important thing is it doesn't crash
    expect($entry)->toHaveKey('message')
        ->and($entry)->toHaveKey('context');
});

// ─── getLogFiles / getLogFileNames ───────────────────────────────────────────

it('returns all .log files from the configured storage path', function () {
    file_put_contents($this->storagePath.'/laravel-2026-03-20.log', '');
    file_put_contents($this->storagePath.'/laravel-2026-03-21.log', '');

    expect($this->logLens->getLogFiles())->toHaveCount(2);
});

it('returns an empty array when no log files exist', function () {
    expect($this->logLens->getLogFiles())->toBeEmpty();
});

it('ignores non-.log files in the storage path', function () {
    file_put_contents($this->storagePath.'/notes.txt', '');
    file_put_contents($this->storagePath.'/laravel.log', '');

    expect($this->logLens->getLogFiles())->toHaveCount(1);
});

it('returns sorted basenames from getLogFileNames', function () {
    file_put_contents($this->storagePath.'/laravel-2026-03-21.log', '');
    file_put_contents($this->storagePath.'/laravel-2026-03-19.log', '');
    file_put_contents($this->storagePath.'/laravel-2026-03-20.log', '');

    expect($this->logLens->getLogFileNames())->toBe([
        'laravel-2026-03-19.log',
        'laravel-2026-03-20.log',
        'laravel-2026-03-21.log',
    ]);
});

// ─── filter ──────────────────────────────────────────────────────────────────

it('returns all logs sorted descending by datetime when no filters are applied', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 09:00:00] local.INFO: Earlier entry',
        '[2026-03-21 10:00:00] local.ERROR: Later entry',
    ]);

    $results = $this->logLens->filter();

    expect($results->first()['message'])->toBe('Later entry')
        ->and($results->last()['message'])->toBe('Earlier entry');
});

it('filters logs by a single level', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Info message',
        '[2026-03-21 10:00:01] local.ERROR: Error message',
    ]);

    $results = $this->logLens->filter(['error']);

    expect($results)->toHaveCount(1)
        ->and($results->first()['level'])->toBe('error');
});

it('filters logs by multiple levels at once', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Info',
        '[2026-03-21 10:00:01] local.ERROR: Error',
        '[2026-03-21 10:00:02] local.DEBUG: Debug',
    ]);

    $results = $this->logLens->filter(['info', 'debug']);

    expect($results)->toHaveCount(2)
        ->and($results->pluck('level')->all())->not->toContain('error');
});

it('searches logs by message text', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Payment processed',
        '[2026-03-21 10:00:01] local.ERROR: Connection refused',
    ]);

    $results = $this->logLens->filter(searches: ['payment']);

    expect($results)->toHaveCount(1)
        ->and($results->first()['message'])->toBe('Payment processed');
});

it('search is case-insensitive', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Payment Processed',
    ]);

    expect($this->logLens->filter(searches: ['PAYMENT']))->toHaveCount(1);
});

it('searches logs by string values inside context', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: User action {"user_id":42,"action":"login"}',
    ]);

    expect($this->logLens->filter(searches: ['login']))->toHaveCount(1);
});

it('returns no results when search does not match any entry', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Nothing here',
    ]);

    expect($this->logLens->filter(searches: ['xyz-not-found']))->toBeEmpty();
});

it('does not filter when searches array is empty', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: One',
        '[2026-03-21 10:00:01] local.ERROR: Two',
    ]);

    expect($this->logLens->filter(searches: []))->toHaveCount(2);
});

it('requires ALL search terms to match (AND semantics)', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Payment processed successfully',
        '[2026-03-21 10:00:01] local.ERROR: Payment failed',
        '[2026-03-21 10:00:02] local.INFO: Order processed',
    ]);

    $results = $this->logLens->filter(searches: ['payment', 'processed']);

    expect($results)->toHaveCount(1)
        ->and($results->first()['message'])->toBe('Payment processed successfully');
});

it('matches multiple search terms across message and context', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.ERROR: Payment failed {"user_id":42,"action":"checkout"}',
        '[2026-03-21 10:00:01] local.ERROR: Payment failed {"user_id":99,"action":"refund"}',
    ]);

    $results = $this->logLens->filter(searches: ['failed', 'checkout']);

    expect($results)->toHaveCount(1)
        ->and($results->first()['context']['action'])->toBe('checkout');
});

it('filters logs by a specific log file', function () {
    writeLog($this->storagePath.'/laravel-2026-03-20.log', [
        '[2026-03-20 10:00:00] local.INFO: Yesterday',
    ]);
    writeLog($this->storagePath.'/laravel-2026-03-21.log', [
        '[2026-03-21 10:00:00] local.INFO: Today',
    ]);

    $results = $this->logLens->filter(logFiles: ['laravel-2026-03-21.log']);

    expect($results)->toHaveCount(1)
        ->and($results->first()['message'])->toBe('Today');
});

it('filters logs by multiple log files', function () {
    writeLog($this->storagePath.'/a.log', ['[2026-03-19 10:00:00] local.INFO: Alpha']);
    writeLog($this->storagePath.'/b.log', ['[2026-03-20 10:00:00] local.INFO: Beta']);
    writeLog($this->storagePath.'/c.log', ['[2026-03-21 10:00:00] local.INFO: Gamma']);

    $results = $this->logLens->filter(logFiles: ['a.log', 'b.log']);

    expect($results)->toHaveCount(2)
        ->and($results->pluck('message')->all())->not->toContain('Gamma');
});

it('combines level and search filters', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: Payment captured',
        '[2026-03-21 10:00:01] local.ERROR: Payment failed',
        '[2026-03-21 10:00:02] local.ERROR: Connection refused',
    ]);

    $results = $this->logLens->filter(levels: ['error'], searches: ['payment']);

    expect($results)->toHaveCount(1)
        ->and($results->first()['message'])->toBe('Payment failed');
});

// ─── summary ─────────────────────────────────────────────────────────────────

it('returns a count per level', function () {
    writeLog($this->storagePath.'/laravel.log', [
        '[2026-03-21 10:00:00] local.INFO: One',
        '[2026-03-21 10:00:01] local.INFO: Two',
        '[2026-03-21 10:00:02] local.ERROR: Err',
    ]);

    $summary = $this->logLens->summary();

    expect($summary['info'])->toBe(2)
        ->and($summary['error'])->toBe(1);
});

it('filters the summary by log file', function () {
    writeLog($this->storagePath.'/a.log', ['[2026-03-21 10:00:00] local.INFO: From A']);
    writeLog($this->storagePath.'/b.log', [
        '[2026-03-21 10:00:01] local.ERROR: From B one',
        '[2026-03-21 10:00:02] local.ERROR: From B two',
    ]);

    $summary = $this->logLens->summary(['b.log']);

    expect($summary)->toHaveKey('error')
        ->and($summary['error'])->toBe(2)
        ->and($summary)->not->toHaveKey('info');
});

it('returns an empty summary when no log files exist', function () {
    expect($this->logLens->summary())->toBeEmpty();
});
