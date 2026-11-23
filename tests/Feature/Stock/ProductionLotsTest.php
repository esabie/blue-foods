<?php

use App\Models\ProductionLot;
use App\Models\User;
use App\Services\Metrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->w = stockWorld();
});

/**
 * @return list<string>
 */
function lotTitlesFor(User $user): array
{
    return $user->fresh()->notifications()->get()->pluck('data.title')->all();
}

test('recording production auto-generates a date-first lot code', function () {
    $this->travelTo(Carbon::parse('2026-10-18 10:00:00', config('inventory.display_timezone')));

    $movement = ledger()->produce(
        $this->w->product,
        20,
        $this->w->warehouse,
        Carbon::parse('2026-10-18'),
        Carbon::parse('2026-11-01'),
    );

    $lot = ProductionLot::sole();
    expect($lot->lot_code)->toBe('20261018-'.$this->w->product->sku.'-'.$movement->id)
        ->and($lot->quantity)->toBe(20)
        ->and($lot->remaining)->toBe(20)
        ->and($lot->expires_on->toDateString())->toBe('2026-11-01');
});

test('warehouse outbound consumes the soonest-expiring lot first', function () {
    $later = ledger()->produce($this->w->product, 10, $this->w->warehouse, now(), now()->addDays(30));
    $soon = ledger()->produce($this->w->product, 10, $this->w->warehouse, now(), now()->addDays(5));

    $laterCode = ProductionLot::where('production_movement_id', $later->id)->value('lot_code');
    $soonCode = ProductionLot::where('production_movement_id', $soon->id)->value('lot_code');

    ledger()->allocateOnline($this->w->product, 6, $this->w->manager);

    expect(ProductionLot::where('lot_code', $soonCode)->value('remaining'))->toBe(4)
        ->and(ProductionLot::where('lot_code', $laterCode)->value('remaining'))->toBe(10);

    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);

    expect(ProductionLot::where('lot_code', $soonCode)->value('remaining'))->toBe(0)
        ->and(ProductionLot::where('lot_code', $laterCode)->value('remaining'))->toBe(9);
});

test('staff can view open lots', function () {
    ledger()->produce($this->w->product, 8, $this->w->warehouse, now(), now()->addDays(10));
    $expected = ProductionLot::sole()->lot_code;

    expect($expected)->toStartWith(now()->format('Ymd').'-');

    $this->actingAs($this->w->warehouse)
        ->get(route('stock.lots'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Stock/Lots')
            ->has('lots', 1)
            ->where('lots.0.lot_code', $expected)
            ->where('lots.0.remaining', 8)
            ->where('lots.0.expiring_soon', true)
            ->where('expiringDays', 14));
});

test('lots:alert-expiring notifies once when a lot enters the window or expires', function () {
    $this->travelTo(Carbon::parse('2026-10-18 10:00:00', config('inventory.display_timezone')));

    ledger()->produce($this->w->product, 5, $this->w->warehouse, now(), now()->addDays(30));
    $soon = ledger()->produce($this->w->product, 7, $this->w->warehouse, now(), now()->addDays(10));
    $expired = ledger()->produce($this->w->product, 3, $this->w->warehouse, now()->subDays(20), now()->subDay());

    Artisan::call('lots:alert-expiring');

    $soonLot = ProductionLot::where('production_movement_id', $soon->id)->sole();
    $expiredLot = ProductionLot::where('production_movement_id', $expired->id)->sole();

    expect($soonLot->expiring_alerted_at)->not->toBeNull()
        ->and($expiredLot->expired_alerted_at)->not->toBeNull()
        ->and(lotTitlesFor($this->w->manager))->toEqualCanonicalizing(['Lot expired', 'Lot expiring soon'])
        ->and(lotTitlesFor($this->w->warehouse))->toEqualCanonicalizing(['Lot expired', 'Lot expiring soon'])
        ->and(lotTitlesFor($this->w->member))->toBe([])
        ->and($this->w->manager->notifications()->where('data->title', 'Lot expiring soon')->first()->data['body'])
            ->toContain($soonLot->lot_code)->toContain('7')
        ->and($this->w->manager->notifications()->where('data->title', 'Lot expired')->first()->data['url'])
            ->toBe('/stock/lots?product_id='.$this->w->product->id);

    Artisan::call('lots:alert-expiring');
    expect(lotTitlesFor($this->w->manager))->toEqualCanonicalizing(['Lot expired', 'Lot expiring soon']);
});

test('overview needsAttention lists open lots that are expiring or expired', function () {
    ledger()->produce($this->w->product, 4, $this->w->warehouse, now(), now()->addDays(5));
    ledger()->produce($this->w->product, 2, $this->w->warehouse, now()->subDays(10), now()->subDays(2));

    $texts = collect(app(Metrics::class)->needsAttention())->pluck('text')->implode(' ');

    expect($texts)->toContain('expire within 14 days')
        ->and($texts)->toContain('expired with stock still on hand');
});
