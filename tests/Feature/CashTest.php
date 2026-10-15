<?php

use App\Enums\HandInMethod;
use App\Exceptions\CashRuleException;
use App\Models\CashHandIn;
use App\Models\User;
use App\Services\CashBook;
use App\Services\Metrics;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function cash(): CashBook
{
    return app(CashBook::class);
}

/**
 * A member holding 20 packs at GHS 25.00 who has sold 4 (GHS 100.00).
 */
beforeEach(function () {
    $this->w = stockWorld();
    ledger()->produce($this->w->product, 20, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 20, $this->w->manager);
    $this->sale = ledger()->recordSale($this->w->member, $this->w->product, 4);
});

test('a member owes their sales less what they have handed in', function () {
    expect(cash()->outstanding($this->w->member))->toBe(10000);

    cash()->record($this->w->member, 6000, HandInMethod::Cash, null, null, $this->w->manager);

    expect(cash()->outstanding($this->w->member))->toBe(4000);
});

test('reversed sales and cancelled hand-ins no longer count', function () {
    $handIn = cash()->record($this->w->member, 10000, HandInMethod::Cash, null, null, $this->w->manager);
    $later = ledger()->recordSale($this->w->member, $this->w->product, 2);
    expect(cash()->outstanding($this->w->member))->toBe(5000);

    ledger()->reverseSale($later, 'Wrong product', $this->w->manager);
    expect(cash()->outstanding($this->w->member))->toBe(0);

    cash()->cancel($handIn, 'Counted wrongly', $this->w->manager);
    expect(cash()->outstanding($this->w->member))->toBe(10000)
        ->and(CashHandIn::count())->toBe(1);
});

test('a hand-in cannot be more than the member owes', function () {
    expect(fn () => cash()->record($this->w->member, 10001, HandInMethod::Cash, null, null, $this->w->manager))
        ->toThrow(CashRuleException::class, 'only owes GHS 100.00');

    cash()->record($this->w->member, 10000, HandInMethod::Cash, null, null, $this->w->manager);

    expect(fn () => cash()->record($this->w->member, 1, HandInMethod::Cash, null, null, $this->w->manager))
        ->toThrow(CashRuleException::class, 'nothing to hand in')
        ->and(fn () => cash()->record($this->w->member, 0, HandInMethod::Cash, null, null, $this->w->manager))
        ->toThrow(CashRuleException::class, 'above zero');
});

test('only marketing team members hand in cash, but a disabled member can still settle up', function () {
    expect(fn () => cash()->record($this->w->warehouse, 100, HandInMethod::Cash, null, null, $this->w->manager))
        ->toThrow(CashRuleException::class);

    $this->w->member->update(['status' => 'disabled']);
    cash()->record($this->w->member->fresh(), 10000, HandInMethod::Cash, null, null, $this->w->manager);

    expect(cash()->outstanding($this->w->member))->toBe(0);
});

test('mobile money needs a transaction ID that has not been used before', function () {
    expect(fn () => cash()->record($this->w->member, 1000, HandInMethod::MobileMoney, '  ', null, $this->w->manager))
        ->toThrow(CashRuleException::class, 'transaction ID');

    $first = cash()->record($this->w->member, 1000, HandInMethod::MobileMoney, ' MP261015.1200.A1 ', null, $this->w->manager);
    expect($first->reference)->toBe('MP261015.1200.A1')
        ->and(fn () => cash()->record($this->w->member, 1000, HandInMethod::MobileMoney, 'MP261015.1200.A1', null, $this->w->manager))
        ->toThrow(CashRuleException::class, "already recorded (hand-in #{$first->id})");

    cash()->cancel($first, 'Wrong member', $this->w->manager);
    cash()->record($this->w->member, 1000, HandInMethod::MobileMoney, 'MP261015.1200.A1', null, $this->w->manager);

    expect(CashHandIn::notCancelled()->count())->toBe(1);
});

test('a cancellation needs a reason, happens once and is audited', function () {
    $handIn = cash()->record($this->w->member, 4000, HandInMethod::Cash, null, null, $this->w->manager);

    expect(fn () => cash()->cancel($handIn, ' ', $this->w->manager))->toThrow(CashRuleException::class, 'reason');

    $this->actingAs($this->w->manager);
    cash()->cancel($handIn, 'Entered twice', $this->w->manager);

    expect(fn () => cash()->cancel($handIn->fresh(), 'Again', $this->w->manager))->toThrow(CashRuleException::class, 'already been cancelled')
        ->and(DB::table('audit_logs')->where('entity', 'cash_hand_in')->where('entity_id', $handIn->id)->pluck('action')->all())
        ->toBe(['created', 'cancelled']);
});

test('hand-ins and cancellations cannot be edited or deleted', function (string $table) {
    $handIn = cash()->record($this->w->member, 4000, HandInMethod::Cash, null, null, $this->w->manager);
    cash()->cancel($handIn, 'Test', $this->w->manager);

    expect(fn () => $handIn->update(['amount_pesewas' => 1]))->toThrow(LogicException::class)
        ->and(fn () => DB::table($table)->update(['created_at' => null]))->toThrow(QueryException::class)
        ->and(fn () => DB::table($table)->delete())->toThrow(QueryException::class);
})->with(['cash_hand_ins', 'cash_hand_in_cancellations']);

test('hand-ins pay off the oldest sales first, and old unpaid sales are flagged', function () {
    $this->travel(3)->days();
    $recent = ledger()->recordSale($this->w->member, $this->w->product, 2);
    $member = cash()->positions()->firstWhere('user_id', $this->w->member->id);

    expect($member['outstanding_pesewas'])->toBe(15000)
        ->and($member['owing_since'])->toBe($this->sale->created_at->toIso8601String())
        ->and($member['overdue'])->toBeTrue()
        ->and(collect(app(Metrics::class)->needsAttention())->pluck('text')->implode(' '))
        ->toContain("{$this->w->member->name} has GHS 150.00 to hand in");

    cash()->record($this->w->member, 10000, HandInMethod::Cash, null, null, $this->w->manager);
    $member = cash()->positions()->firstWhere('user_id', $this->w->member->id);

    expect($member['owing_since'])->toBe($recent->created_at->toIso8601String())
        ->and($member['overdue'])->toBeFalse()
        ->and(collect(app(Metrics::class)->needsAttention())->pluck('route'))->not->toContain('cash.index');
});

test('the member hears about each hand-in and cancellation', function () {
    $handIn = cash()->record($this->w->member, 6000, HandInMethod::MobileMoney, 'MP1', null, $this->w->manager);
    cash()->cancel($handIn, 'Payment bounced', $this->w->manager);

    $notes = $this->w->member->fresh()->notifications()->get()->pluck('data')->keyBy('title');
    expect($notes->keys()->sort()->values()->all())->toBe(['Cash hand-in cancelled', 'Cash hand-in recorded', 'Stock assigned to you'])
        ->and($notes['Cash hand-in recorded']['body'])->toContain($this->w->manager->name)->toContain('GHS 60.00')->toContain('ref MP1')->toContain('Still to hand in: GHS 40.00')
        ->and($notes['Cash hand-in cancelled']['body'])->toContain('Payment bounced')
        ->and($this->w->manager->notifications()->count())->toBe(0);
});

test('management records a hand-in in cedis from the cash page', function () {
    $this->actingAs($this->w->manager)
        ->post(route('cash.store'), ['member_id' => $this->w->member->id, 'amount' => '62.5', 'method' => 'cash'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', "Recorded GHS 62.50 from {$this->w->member->name}.");

    expect(CashHandIn::sole()->amount_pesewas)->toBe(6250);

    $this->post(route('cash.store'), ['member_id' => $this->w->member->id, 'amount' => '50', 'method' => 'cash'])
        ->assertSessionHasErrors(['amount' => "{$this->w->member->name} only owes GHS 37.50."]);
    $this->post(route('cash.store'), ['member_id' => $this->w->member->id, 'amount' => '1,000', 'method' => 'cash'])
        ->assertSessionHasErrors('amount');
    $this->post(route('cash.store'), ['member_id' => $this->w->warehouse->id, 'amount' => '1', 'method' => 'cash'])
        ->assertSessionHasErrors('member_id');
    $this->post(route('cash.store'), ['member_id' => $this->w->member->id, 'amount' => '1', 'method' => 'mobile_money'])
        ->assertSessionHasErrors('reference');

    $this->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Cash/Index')
        ->where('totals.outstanding_pesewas', 3750)
        ->where('totals.owing', 1)
        ->where('totals.today_pesewas', 6250)
        ->where('positions.0.handed_in_pesewas', 6250)
        ->where('handIns.data.0.method_label', 'Cash')
    );
});

test('the member sees what they still owe and their recent hand-ins', function () {
    cash()->record($this->w->member, 2500, HandInMethod::Cash, null, null, $this->w->manager);

    $this->actingAs($this->w->member)->get(route('member.home'))->assertInertia(fn (Assert $page) => $page
        ->where('cash.outstanding_pesewas', 7500)
        ->where('cash.owing_since', $this->sale->created_at->toIso8601String())
        ->where('cash.overdue', false)
        ->where('recentHandIns.0.amount_pesewas', 2500)
        ->where('recentHandIns.0.received_by', $this->w->manager->name)
    );
});

test('the cash report lists the period\'s hand-ins and exports the same figures', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 18:05', 'UTC'));
    $kept = cash()->record($this->w->member, 6000, HandInMethod::MobileMoney, 'MP1', 'Evening', $this->w->manager);
    $this->travel(10)->minutes();
    $twice = cash()->record($this->w->member, 2500, HandInMethod::Cash, null, null, $this->w->manager);
    cash()->cancel($twice, 'Entered twice', $this->w->manager);
    $this->travelTo(CarbonImmutable::parse('2026-04-01 09:00', 'UTC'));
    cash()->record($this->w->member, 1000, HandInMethod::Cash, null, null, $this->w->manager);

    $query = ['type' => 'cash-hand-ins', 'from' => '2026-03-01', 'to' => '2026-03-31'];
    $this->actingAs($this->w->manager)->get(route('reports.show', $query))->assertInertia(fn (Assert $page) => $page
        ->where('report.title', 'Cash hand-ins')
        ->has('report.rows', 2)
        ->where('report.rows.0.id', $kept->id)
        ->where('report.rows.1.amount', null)
        ->where('report.rows.1.cancelled', 2500)
        ->where('report.totals', ['id' => 'Total', 'amount' => 6000, 'cancelled' => 2500])
    );

    $csv = $this->get(route('reports.export', $query))
        ->assertHeader('content-disposition', 'attachment; filename=blue-foods-cash-hand-ins-2026-03-01-to-2026-03-31.csv')
        ->streamedContent();
    $name = $this->w->member->name;
    $manager = $this->w->manager->name;

    expect(array_map('str_getcsv', explode("\n", trim(substr($csv, 3)))))->toBe([
        ['Hand-in #', 'Date and time', 'Team member', 'Method', 'Transaction ID', 'Received by', 'Amount (GHS)', 'Cancelled (GHS)', 'Note'],
        [(string) $kept->id, '2026-03-02 18:05', $name, 'Mobile money', 'MP1', $manager, '60.00', '', 'Evening'],
        [(string) $twice->id, '2026-03-02 18:15', $name, 'Cash', '', $manager, '', '25.00', "Cancelled by {$manager}: Entered twice"],
        ['Total', '', '', '', '', '', '60.00', '25.00', ''],
    ]);
});

test('other members cannot see each other\'s cash', function () {
    $other = User::factory()->marketing()->create();
    cash()->record($this->w->member, 2500, HandInMethod::Cash, null, null, $this->w->manager);

    $this->actingAs($other)->get(route('member.home'))->assertInertia(fn (Assert $page) => $page
        ->where('cash.outstanding_pesewas', 0)
        ->where('recentHandIns', [])
    );
});
