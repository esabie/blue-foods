<?php

use App\Enums\AdjustmentReason;
use App\Models\User;
use App\Services\StaffNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Titles of a user's notifications, newest first.
 *
 * @return list<string>
 */
function titlesFor(User $user): array
{
    return $user->fresh()->notifications()->get()->pluck('data.title')->all();
}

test('a pay-on-delivery order tells management and warehouse, nobody else', function () {
    $w = stockWorld();
    $disabledManager = User::factory()->management()->disabled()->create();
    User::systemAccount();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->allocateOnline($w->product, 5, $w->manager);

    $order = placeOnlineOrder([$w->product->id => 2], 'pay_on_delivery');

    foreach ([$w->manager, $w->warehouse] as $user) {
        $note = $user->notifications()->first();
        expect($note->data['title'])->toBe('New online order to pack')
            ->and($note->data['body'])->toContain($order->reference)->toContain('pay on delivery')
            ->and($note->data['url'])->toBe('/online-orders?tab=to_pack');
    }
    expect(titlesFor($w->member))->toBe([])
        ->and(titlesFor($disabledManager))->toBe([])
        ->and(User::systemAccount()->notifications()->count())->toBe(0);
});

test('an online payment notifies once, and an unpaid order notifies nobody', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->allocateOnline($w->product, 5, $w->manager);

    $order = placeOnlineOrder([$w->product->id => 1]);
    expect(titlesFor($w->warehouse))->toBe([]);

    $payment = shop()->startPayment($order, 'sandbox');
    shop()->confirmPayment($payment, 'P1', $order->total_pesewas);
    shop()->confirmPayment($payment, 'P1', $order->total_pesewas);

    expect(titlesFor($w->warehouse))->toBe(['New online order to pack'])
        ->and($w->warehouse->notifications()->first()->data['body'])->toContain('(paid)');
});

test('a payment after the stock was released asks management for a refund', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->allocateOnline($w->product, 1, $w->manager);

    $late = placeOnlineOrder([$w->product->id => 1]);
    $payment = shop()->startPayment($late, 'sandbox');
    $this->travel(16)->minutes();
    placeOnlineOrder([$w->product->id => 1], 'pay_on_delivery');

    shop()->confirmPayment($payment, 'P1', $late->total_pesewas);

    expect(titlesFor($w->manager))->toContain('Refund needed')
        ->and(titlesFor($w->warehouse))->not->toContain('Refund needed');
});

test('returns notify the warehouse when asked and the member when decided', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 6, $w->manager);

    $first = ledger()->requestReturn($w->member, $w->product, 2);
    $second = ledger()->requestReturn($w->member, $w->product, 1);

    expect(titlesFor($w->warehouse))->toBe(['Return to confirm', 'Return to confirm'])
        ->and($w->warehouse->notifications()->first()->data['body'])->toContain($w->member->name);

    ledger()->confirmReturn($first, $w->warehouse);
    ledger()->rejectReturn($second, $w->warehouse, 'Packs were damaged');

    $member = $w->member->fresh()->notifications()->get()->keyBy('data.title');
    expect($member->keys()->all())->toContain('Return received', 'Return not accepted')
        ->and($member['Return not accepted']->data['body'])->toContain('Packs were damaged');
});

test('a member hears about stock assigned to them and sales reversed', function () {
    $w = stockWorld(4200);
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 4, $w->manager);
    $sale = ledger()->recordSale($w->member, $w->product, 1);
    ledger()->reverseSale($sale, 'Entered twice', $w->manager);

    $notes = $w->member->fresh()->notifications()->get()->keyBy('data.title');

    expect($notes['Stock assigned to you']->data['body'])->toContain('4')->toContain('GHS 42.00')
        ->and($notes['Sale reversed']->data['body'])->toContain('Entered twice')
        ->and(titlesFor($w->manager))->toBe([]);
});

test('low stock is reported once, when the unassigned balance first drops below the reorder level', function () {
    $w = stockWorld();
    $w->product->update(['reorder_level' => 10]);
    ledger()->produce($w->product, 15, $w->warehouse);

    ledger()->assign($w->product, $w->member, 3, $w->manager);
    expect(titlesFor($w->manager))->toBe([]);

    ledger()->allocateOnline($w->product, 3, $w->manager);
    ledger()->adjust($w->product, 1, false, AdjustmentReason::Damaged, null, $w->warehouse);

    expect(titlesFor($w->manager))->toBe(['Low stock'])
        ->and(titlesFor($w->warehouse))->toBe(['Low stock'])
        ->and($w->manager->notifications()->first()->data['body'])->toContain('9')->toContain('reorder level of 10');
});

test('a rolled-back operation leaves no notification behind', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);

    try {
        DB::transaction(function () use ($w) {
            ledger()->assign($w->product, $w->member, 2, $w->manager);
            throw new RuntimeException('Something later failed');
        });
    } catch (RuntimeException) {
    }

    expect(titlesFor($w->member))->toBe([]);
});

test('nothing is sent while quiet, e.g. when seeding history', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);

    app(StaffNotifier::class)->quietly(fn () => ledger()->assign($w->product, $w->member, 2, $w->manager));
    expect(titlesFor($w->member))->toBe([]);

    ledger()->assign($w->product, $w->member, 1, $w->manager);
    expect(titlesFor($w->member))->toBe(['Stock assigned to you']);
});

test('the bell lists only your own notifications and marks them read', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 2, $w->manager);
    ledger()->assign($w->product, $w->member, 1, $w->manager);
    ledger()->requestReturn($w->member, $w->product, 1);

    $this->actingAs($w->member)->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread', 2)
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0.url', route('member.home', absolute: false));

    $mine = $w->member->notifications()->first();
    $theirs = $w->warehouse->notifications()->first();

    $this->actingAs($w->member)->postJson(route('notifications.read', $theirs->id))->assertNotFound();
    expect($theirs->fresh()->read_at)->toBeNull();

    $this->actingAs($w->member)->postJson(route('notifications.read', $mine->id))
        ->assertOk()
        ->assertJsonPath('unread', 1);

    $this->actingAs($w->member)->postJson(route('notifications.read-all'))->assertJsonPath('unread', 0);
    expect($w->warehouse->unreadNotifications()->count())->toBe(1);
});

test('every staff page shares the unread count', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 2, $w->manager);

    $this->actingAs($w->member)->get(route('member.home'))
        ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
});
