<?php

use AniketMagadum\LogLens\LogLens;
use AniketMagadum\LogLens\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

// ─── Setup / Teardown ────────────────────────────────────────────────────────

beforeEach(function () {
    $this->storagePath = storage_path('logs/test-'.uniqid());
    mkdir($this->storagePath, 0755, true);

    $this->bindLogLens = function (int $perPage = 50): void {
        app()->instance(LogLens::class, new LogLens([
            'storage_path' => $this->storagePath,
            'per_page' => $perPage,
        ]));
        config([
            'log-lens.storage_path' => $this->storagePath,
            'log-lens.per_page' => $perPage,
        ]);
    };

    ($this->bindLogLens)();
});

afterEach(function () {
    File::deleteDirectory($this->storagePath);
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

it('renders the log lens dashboard with a 200 response', function () {
    $this->get('/log-lens')->assertOk();
});

it('shows a message when there are no log entries', function () {
    $this->get('/log-lens')->assertOk()->assertSee('No log entries');
});

it('renders log entries on the dashboard', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] production.ERROR: Database connection failed\n"
    );
    ($this->bindLogLens)();

    $this->get('/log-lens')
        ->assertOk()
        ->assertSee('Database connection failed');
});

it('displays the log file name in the file list', function () {
    file_put_contents($this->storagePath.'/laravel-2026-03-21.log', "[2026-03-21 10:00:00] local.INFO: Hello\n");
    ($this->bindLogLens)();

    $this->get('/log-lens')
        ->assertOk()
        ->assertSee('laravel-2026-03-21.log');
});

// ─── Level Filter ────────────────────────────────────────────────────────────

it('filters log entries by level', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: Info message\n".
        "[2026-03-21 10:00:01] local.ERROR: Error message\n"
    );
    ($this->bindLogLens)();

    $this->get('/log-lens?level[]=error')
        ->assertOk()
        ->assertSee('Error message')
        ->assertDontSee('Info message');
});

it('filters log entries by multiple levels', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: Info message\n".
        "[2026-03-21 10:00:01] local.ERROR: Error message\n".
        "[2026-03-21 10:00:02] local.DEBUG: Debug message\n"
    );
    ($this->bindLogLens)();

    $this->get('/log-lens?level[]=info&level[]=debug')
        ->assertOk()
        ->assertSee('Info message')
        ->assertSee('Debug message')
        ->assertDontSee('Error message');
});

// ─── Search Filter ───────────────────────────────────────────────────────────

it('filters log entries by a single search term', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: Payment processed\n".
        "[2026-03-21 10:00:01] local.ERROR: Connection refused\n"
    );
    ($this->bindLogLens)();

    $this->get('/log-lens?search[]=payment')
        ->assertOk()
        ->assertSee('Payment processed')
        ->assertDontSee('Connection refused');
});

it('accepts a legacy plain search string for backward compatibility', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: Payment processed\n".
        "[2026-03-21 10:00:01] local.ERROR: Connection refused\n"
    );
    ($this->bindLogLens)();

    $this->get('/log-lens?search=payment')
        ->assertOk()
        ->assertSee('Payment processed')
        ->assertDontSee('Connection refused');
});

it('filters log entries by multiple search terms (AND semantics)', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: Payment processed successfully\n".
        "[2026-03-21 10:00:01] local.ERROR: Payment failed\n".
        "[2026-03-21 10:00:02] local.INFO: Order processed\n"
    );
    ($this->bindLogLens)();

    $this->get('/log-lens?search[]=payment&search[]=processed')
        ->assertOk()
        ->assertSee('Payment processed successfully')
        ->assertDontSee('Payment failed')
        ->assertDontSee('Order processed');
});

it('renders active search chips in the view', function () {
    ($this->bindLogLens)();

    $this->get('/log-lens?search[]=foobar&search[]=bazqux')
        ->assertOk()
        ->assertSee('foobar')
        ->assertSee('bazqux');
});

// ─── Log File Filter ─────────────────────────────────────────────────────────

it('filters log entries by a specific log file', function () {
    file_put_contents($this->storagePath.'/a.log', "[2026-03-21 10:00:00] local.INFO: From alpha file\n");
    file_put_contents($this->storagePath.'/b.log', "[2026-03-21 10:00:01] local.INFO: From beta file\n");
    ($this->bindLogLens)();

    $this->get('/log-lens?log_file[]=a.log')
        ->assertOk()
        ->assertSee('From alpha file')
        ->assertDontSee('From beta file');
});

// ─── Pagination ──────────────────────────────────────────────────────────────

it('paginates log entries', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: Entry one\n".
        "[2026-03-21 10:00:01] local.INFO: Entry two\n".
        "[2026-03-21 10:00:02] local.INFO: Entry three\n"
    );
    ($this->bindLogLens)(2); // 2 per page

    // Sorted descending: page 1 has "Entry three" + "Entry two"; page 2 has "Entry one"
    $this->get('/log-lens?page=2')
        ->assertOk()
        ->assertSee('Entry one')
        ->assertDontSee('Entry three');
});

it('shows the correct total count and page info', function () {
    file_put_contents(
        $this->storagePath.'/laravel.log',
        "[2026-03-21 10:00:00] local.INFO: First\n".
        "[2026-03-21 10:00:01] local.INFO: Second\n".
        "[2026-03-21 10:00:02] local.INFO: Third\n"
    );
    ($this->bindLogLens)(2);

    $this->get('/log-lens')
        ->assertOk()
        ->assertSee('3 total entries');
});

// ─── Container / Service Provider ────────────────────────────────────────────

it('resolves LogLens from the service container', function () {
    expect(app(LogLens::class))->toBeInstanceOf(LogLens::class);
});

it('resolves LogLens via its log-lens alias', function () {
    expect(app('log-lens'))->toBeInstanceOf(LogLens::class);
});
