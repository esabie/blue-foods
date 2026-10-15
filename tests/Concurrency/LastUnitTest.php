<?php

use App\Models\CashHandIn;
use App\Models\OnlineOrder;
use App\Models\Sale;
use App\Services\CashBook;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
| B7: concurrent requests cannot oversell. Several separate PHP processes race to sell
| the last unit against a real MySQL database; exactly one may succeed.
| Skipped when MySQL is not reachable. Uses a throwaway database (CONCURRENCY_DB_DATABASE).
*/

beforeEach(function () {
    $this->database = env('CONCURRENCY_DB_DATABASE', 'blueFoods_concurrency');
    $mysql = config('database.connections.mysql');

    try {
        $pdo = new PDO("mysql:host={$mysql['host']};port={$mysql['port']}", $mysql['username'], $mysql['password']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->database}`");
    } catch (PDOException $e) {
        $this->markTestSkipped('MySQL is not reachable: '.$e->getMessage());
    }

    config([
        'database.connections.concurrency' => [...$mysql, 'database' => $this->database],
        'database.default' => 'concurrency',
    ]);
    DB::purge('concurrency');

    Artisan::call('migrate:fresh', ['--database' => 'concurrency', '--drop-views' => true, '--force' => true]);
});

afterEach(function () {
    if (config('database.default') === 'concurrency') {
        DB::connection('concurrency')->statement("DROP DATABASE IF EXISTS `{$this->database}`");
        DB::purge('concurrency');
    }
});

/**
 * Start several copies of a worker script at the same moment and collect what each printed.
 *
 * @param  list<string>  $args
 * @return Collection<int, string>
 */
function race(string $script, array $args, int $workers = 4): Collection
{
    $mysql = config('database.connections.concurrency');
    $startAt = microtime(true) + 2;

    $processes = collect(range(1, $workers))->map(function () use ($script, $args, $mysql, $startAt) {
        $process = new Process(
            [PHP_BINARY, __DIR__.'/'.$script, ...$args, (string) $startAt],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $mysql['host'],
                'DB_PORT' => (string) $mysql['port'],
                'DB_DATABASE' => $mysql['database'],
                'DB_USERNAME' => $mysql['username'],
                'DB_PASSWORD' => $mysql['password'],
            ],
            timeout: 60,
        );
        $process->start();

        return $process;
    });

    return $processes->map(function (Process $process) {
        $process->wait();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        return trim($process->getOutput());
    });
}

test('B7: only one of several simultaneous sales for the last unit succeeds', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 1, $w->warehouse);
    ledger()->assign($w->product, $w->member, 1, $w->manager);

    $results = race('sell_last_unit.php', [(string) $w->member->id, (string) $w->product->id]);

    expect($results->filter(fn ($r) => $r === 'sold'))->toHaveCount(1)
        ->and($results->filter(fn ($r) => $r === 'refused'))->toHaveCount(3)
        ->and(Sale::count())->toBe(1)
        ->and(ledger()->balance($w->product->id, $w->member->location->id))->toBe(0);
});

test('only one of several simultaneous hand-ins of the whole balance is recorded', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 2, $w->warehouse);
    ledger()->assign($w->product, $w->member, 2, $w->manager);
    ledger()->recordSale($w->member, $w->product, 2);

    $results = race('hand_in_full_balance.php', [(string) $w->member->id, (string) $w->manager->id, '5000']);

    expect($results->filter(fn ($r) => $r === 'recorded'))->toHaveCount(1)
        ->and($results->filter(fn ($r) => $r === 'refused'))->toHaveCount(3)
        ->and(CashHandIn::count())->toBe(1)
        ->and(app(CashBook::class)->outstanding($w->member))->toBe(0);
});

test('G5/B7: only one of several simultaneous online orders for the last unit succeeds', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 1, $w->warehouse);
    ledger()->allocateOnline($w->product, 1, $w->manager);

    $results = race('order_last_online_unit.php', [(string) $w->product->id]);

    expect($results->filter(fn ($r) => $r === 'ordered'))->toHaveCount(1)
        ->and($results->filter(fn ($r) => $r === 'refused'))->toHaveCount(3)
        ->and(OnlineOrder::count())->toBe(1)
        ->and(shop()->availability([$w->product->id])[$w->product->id])->toBe(0);
});
