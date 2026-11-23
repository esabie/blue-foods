<?php

use App\Enums\StockRequestStatus;
use App\Exceptions\StockRuleException;
use App\Models\StockRequest;
use App\Services\Metrics;

beforeEach(function () {
    $this->w = stockWorld();
});

test('a member can request stock and the warehouse can assign it', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);

    $this->actingAs($this->w->member)->post(route('member.stock-requests.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 3,
        'note' => 'Running low for the weekend',
    ])->assertSessionHasNoErrors();

    $request = StockRequest::sole();
    expect($request->status)->toBe(StockRequestStatus::Requested)
        ->and($request->member_note)->toBe('Running low for the weekend')
        ->and(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(0);

    $this->actingAs($this->w->warehouse)
        ->post(route('stock-requests.confirm', $request), ['quantity' => 3])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(StockRequestStatus::Confirmed)
        ->and($request->fresh()->confirmed_by)->toBe($this->w->warehouse->id)
        ->and(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(3)
        ->and(ledger()->balance($this->w->product->id, \App\Models\Location::warehouse()->id))->toBe(7);
});

test('warehouse can assign less than requested', function () {
    ledger()->produce($this->w->product, 200, $this->w->warehouse);
    $request = ledger()->requestStock($this->w->member, $this->w->product, 100);

    $this->actingAs($this->w->warehouse)
        ->post(route('stock-requests.confirm', $request), ['quantity' => 90])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(StockRequestStatus::Confirmed)
        ->and($request->fresh()->quantity)->toBe(100)
        ->and($request->fresh()->movement->quantity)->toBe(90)
        ->and($request->fresh()->decision_note)->toBe('Requested 100; assigned 90.')
        ->and(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(90);
});

test('warehouse cannot assign more than requested or more than unassigned', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    $request = ledger()->requestStock($this->w->member, $this->w->product, 10);

    expect(fn () => ledger()->confirmStockRequest($request, $this->w->warehouse, 11))
        ->toThrow(StockRuleException::class, 'only asked for')
        ->and(fn () => ledger()->confirmStockRequest($request, $this->w->warehouse, 6))
        ->toThrow(StockRuleException::class);
});

test('warehouse can decline a request with a required note when stock is short', function () {
    ledger()->produce($this->w->product, 2, $this->w->warehouse);
    $request = ledger()->requestStock($this->w->member, $this->w->product, 5, 'Need five packs');

    $this->actingAs($this->w->warehouse)
        ->post(route('stock-requests.reject', $request), ['note' => ''])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->w->warehouse)
        ->post(route('stock-requests.reject', $request), [
            'note' => 'Only 2 packs left; more after tomorrow\'s production.',
        ])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(StockRequestStatus::Rejected)
        ->and($request->fresh()->decision_note)->toContain('Only 2 packs')
        ->and(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(0);
});

test('a member with overdue cash cannot request more stock', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 4, $this->w->manager);
    ledger()->recordSale($this->w->member, $this->w->product, 2);

    $this->travel(3)->days();

    $this->actingAs($this->w->member)->post(route('member.stock-requests.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 2,
    ])->assertSessionHasErrors('member');

    expect(fn () => ledger()->requestStock($this->w->member, $this->w->product, 1))
        ->toThrow(StockRuleException::class, 'Hand in your overdue cash');
});

test('a confirmed or rejected request cannot be decided again', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    $first = ledger()->requestStock($this->w->member, $this->w->product, 2);
    ledger()->confirmStockRequest($first, $this->w->warehouse);
    expect(fn () => ledger()->confirmStockRequest($first, $this->w->warehouse))
        ->toThrow(StockRuleException::class);

    $second = ledger()->requestStock($this->w->member, $this->w->product, 1);
    ledger()->rejectStockRequest($second, $this->w->warehouse, 'Not available yet');
    expect(fn () => ledger()->rejectStockRequest($second, $this->w->warehouse, 'Again'))
        ->toThrow(StockRuleException::class);
});

test('pending stock requests surface on the overview attention list', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->requestStock($this->w->member, $this->w->product, 2);

    expect(collect(app(Metrics::class)->needsAttention())->pluck('route'))
        ->toContain('stock-requests.index');
});

test('the member home includes requestable products and pending requests', function () {
    ledger()->produce($this->w->product, 8, $this->w->warehouse);
    ledger()->requestStock($this->w->member, $this->w->product, 2, 'Please');

    $this->actingAs($this->w->member)->get(route('member.home'))
        ->assertInertia(fn ($page) => $page
            ->component('Member/Home')
            ->has('requestableProducts')
            ->has('pendingStockRequests', 1)
            ->where('pendingStockRequests.0.quantity', 2)
            ->where('cash.overdue', false));
});

test('member home suggests requesting more when holdings are low', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 2, $this->w->manager);

    $this->actingAs($this->w->member)->get(route('member.home'))
        ->assertInertia(fn ($page) => $page
            ->has('stockSuggestions', 1)
            ->where('stockSuggestions.0.id', $this->w->product->id)
            ->where('stockSuggestions.0.held_available', 2));
});

test('stock requests appear on the management report', function () {
    ledger()->produce($this->w->product, 20, $this->w->warehouse);
    $request = ledger()->requestStock($this->w->member, $this->w->product, 10);
    ledger()->confirmStockRequest($request, $this->w->warehouse, 7);

    $this->actingAs($this->w->manager)
        ->get(route('reports.show', [
            'type' => 'stock-requests',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Show')
            ->where('report.type', 'stock-requests')
            ->has('report.rows', 1)
            ->where('report.rows.0.requested', 10)
            ->where('report.rows.0.assigned', 7)
            ->where('report.rows.0.status', 'confirmed'));
});
