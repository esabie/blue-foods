<?php

/**
 * Worker for LastUnitTest: boots the app in its own process and tries to record a hand-in of the member's whole balance.
 * Usage: php hand_in_full_balance.php <memberId> <managerId> <amountPesewas> <startAt>
 */

use App\Enums\HandInMethod;
use App\Exceptions\CashRuleException;
use App\Models\User;
use App\Services\CashBook;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$_, $memberId, $managerId, $amount, $startAt] = $argv;

// Widen the race window: pause after reading the balance and before writing the hand-in.
DB::listen(function (QueryExecuted $query) {
    if (stripos($query->sql, 'sum(') !== false && str_contains($query->sql, 'cash_hand_ins')) {
        usleep(300_000);
    }
});

$member = User::findOrFail((int) $memberId);
$manager = User::findOrFail((int) $managerId);

while (microtime(true) < (float) $startAt) {
    usleep(1_000);
}

try {
    app(CashBook::class)->record($member, (int) $amount, HandInMethod::Cash, null, null, $manager);
    echo 'recorded';
} catch (CashRuleException) {
    echo 'refused';
}
