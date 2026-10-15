<?php

use App\Enums\LocationType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->manager = User::factory()->management()->create();
});

test('P1: management creates a product with a price in pesewas', function () {
    $this->actingAs($this->manager)->post(route('products.store'), [
        'sku' => 'BF-TIL-500',
        'name' => 'Smoked tilapia',
        'category' => 'Smoked fish',
        'unit' => 'pack',
        'reorder_level' => 20,
        'price' => '35.50',
        'photo' => UploadedFile::fake()->image('tilapia.jpg'),
    ])->assertSessionHasNoErrors();

    $product = Product::where('sku', 'BF-TIL-500')->sole();
    expect($product->currentPricePesewas())->toBe(3550)
        ->and($product->currentPrice->set_by)->toBe($this->manager->id);
});

test('P1: SKU codes are unique', function () {
    Product::factory()->priced()->create(['sku' => 'BF-1']);

    $this->actingAs($this->manager)->post(route('products.store'), [
        'sku' => 'BF-1', 'name' => 'X', 'category' => 'Y', 'unit' => 'pack', 'reorder_level' => 0, 'price' => '1',
    ])->assertSessionHasErrors('sku');
});

test('P2: price history is kept with effective dates', function () {
    $product = Product::factory()->priced(1000)->create();

    $this->travel(1)->minutes();
    $this->actingAs($this->manager)->post(route('products.price', $product), ['price' => '12.00']);

    expect($product->prices()->pluck('unit_price_pesewas')->all())->toBe([1200, 1000])
        ->and($product->currentPricePesewas())->toBe(1200);
});

test('a product can be created with a cost, which is optional', function () {
    $fields = ['category' => 'Fish', 'unit' => 'pack', 'reorder_level' => 0, 'price' => '35.50'];
    $this->actingAs($this->manager)->post(route('products.store'), [
        'sku' => 'BF-1', 'name' => 'With cost', 'cost' => '20.25', 'photo' => UploadedFile::fake()->image('a.jpg'), ...$fields,
    ])->assertSessionHasNoErrors();
    $this->post(route('products.store'), [
        'sku' => 'BF-2', 'name' => 'No cost', 'cost' => '', 'photo' => UploadedFile::fake()->image('b.jpg'), ...$fields,
    ])->assertSessionHasNoErrors();

    expect(Product::where('sku', 'BF-1')->sole()->currentCost->unit_cost_pesewas)->toBe(2025)
        ->and(Product::where('sku', 'BF-2')->sole()->costs()->exists())->toBeFalse();
});

test('cost changes are kept as history, may be backdated but not set in the future, and are audited', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 09:00', 'UTC'));
    $product = Product::factory()->priced(3000)->create();

    $this->actingAs($this->manager)->post(route('products.cost', $product), ['cost' => '15', 'effective_from' => '2026-01-01'])->assertSessionHasNoErrors();
    $this->post(route('products.cost', $product), ['cost' => '16.50', 'effective_from' => '2026-03-10'])->assertSessionHasNoErrors();
    $this->post(route('products.cost', $product), ['cost' => '17', 'effective_from' => '2026-03-11'])->assertSessionHasErrors('effective_from');
    $this->post(route('products.cost', $product), ['cost' => '1.234'])->assertSessionHasErrors('cost');
    $this->post(route('products.cost', $product), [])->assertSessionHasErrors('cost');

    expect($product->costs->map(fn ($c) => [$c->unit_cost_pesewas, $c->effective_from->toDateTimeString()])->all())->toBe([
        [1650, '2026-03-10 09:00:00'],
        [1500, '2026-01-01 00:00:00'],
    ])->and($product->fresh()->currentCost->unit_cost_pesewas)->toBe(1650)
        ->and(AuditLog::where('entity', 'product_cost')->where('action', 'created')->count())->toBe(2)
        ->and(fn () => $product->costs->first()->update(['unit_cost_pesewas' => 1]))->toThrow(LogicException::class);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('products.0.cost_pesewas', 1650)
        ->where('products.0.cost_history.1.unit_cost_pesewas', 1500)
        ->where('products.0.cost_history.0.set_by', $this->manager->name));
});

test('the users page shows each member\'s stock, cash to hand in and last sign-in', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00', 'UTC'));
    $w = stockWorld(2500);
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 6, $w->manager);
    ledger()->recordSale($w->member, $w->product, 2);

    $this->travelTo(CarbonImmutable::parse('2026-03-10 09:00', 'UTC'));
    $this->post(route('login'), ['email' => $w->member->email, 'password' => 'password'])->assertRedirect();
    $this->post(route('logout'));

    expect($w->member->fresh()->last_login_at->toDateTimeString())->toBe('2026-03-10 09:00:00')
        ->and(AuditLog::where('entity', 'user')->where('action', 'updated')->exists())->toBeFalse();

    $users = collect($this->actingAs($w->manager)->get(route('users.index'))->viewData('page')['props']['users'])->keyBy('id');
    expect($users[$w->member->id]['team'])->toBe([
        'units_held' => 4,
        'cash_owed_pesewas' => 5000,
        'cash_overdue' => true,
        'last_sale_at' => '2026-03-01T10:00:00+00:00',
    ])->and($users[$w->member->id]['last_login_at'])->toBe('2026-03-10T09:00:00+00:00')
        ->and($users[$w->manager->id]['team'])->toBeNull()
        ->and($users[$w->manager->id]['last_login_at'])->toBeNull();
});

test('the products page shows where the stock is and recent sales', function () {
    $w = stockWorld();
    $w->product->update(['reorder_level' => 5]);
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 6, $w->manager);
    ledger()->allocateOnline($w->product, 1, $w->manager);
    ledger()->recordSale($w->member, $w->product, 2);

    $this->actingAs($w->manager)->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('products.0.stock', [
            'unassigned' => 3, 'with_team' => 4, 'online' => 1, 'remaining' => 8, 'low_stock' => true, 'sold_30_days' => 2,
        ]));
});

test('U1: creating a marketing user gives them their own stock location', function () {
    $this->actingAs($this->manager)->post(route('users.store'), [
        'name' => 'Kofi Mensah',
        'email' => 'Kofi@BlueFoods.test',
        'phone' => '024 555 1234',
        'role' => 'marketing',
        'password' => 'temporary-pass',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'kofi@bluefoods.test')->sole();
    expect($user->location->type)->toBe(LocationType::Member)
        ->and($user->phone)->toBe('+233245551234');
});

test('U1: a user needs an email to sign in with; a phone number is optional', function () {
    $this->actingAs($this->manager)->post(route('users.store'), [
        'name' => 'Phone Only', 'phone' => '024 555 1234', 'role' => 'warehouse', 'password' => 'temporary-pass',
    ])->assertSessionHasErrors('email');

    $this->post(route('users.store'), [
        'name' => 'Email Only', 'email' => 'desk@bluefoods.test', 'role' => 'warehouse', 'password' => 'temporary-pass',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'desk@bluefoods.test')->sole()->phone)->toBeNull();
});

test('U1: management disables a user and resets passwords', function () {
    $user = User::factory()->warehouse()->create();
    $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

    $this->actingAs($this->manager)->post(route('users.disable', $user));
    expect($user->fresh()->status)->toBe(UserStatus::Disabled);

    $this->actingAs($this->manager)->post(route('users.reset-password', $user), ['password' => 'brand-new-pass']);
    expect($user->fresh()->isLocked())->toBeFalse()
        ->and(password_verify('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

test('U1: management cannot disable their own account', function () {
    $this->actingAs($this->manager)->post(route('users.disable', $this->manager))->assertSessionHasErrors('user');

    expect($this->manager->fresh()->isActive())->toBeTrue();
});

test('U4: the audit log records who changed what, with before and after values', function () {
    $product = Product::factory()->priced()->create(['name' => 'Old name']);

    $this->actingAs($this->manager)->put(route('products.update', $product), [
        'sku' => $product->sku, 'name' => 'New name', 'category' => $product->category,
        'unit' => $product->unit, 'reorder_level' => 5,
    ]);

    $log = AuditLog::where('entity', 'product')->where('action', 'updated')->sole();
    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->before_json)->toMatchArray(['name' => 'Old name', 'reorder_level' => 0])
        ->and($log->after_json)->toMatchArray(['name' => 'New name', 'reorder_level' => 5]);
});

test('U4: sale reversals and passwords are handled correctly in the audit log', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 2, $w->warehouse);
    ledger()->assign($w->product, $w->member, 2, $w->manager);
    $sale = ledger()->recordSale($w->member, $w->product, 1);

    $this->actingAs($w->manager)->post(route('sales.reverse', $sale), ['reason' => 'Duplicate']);

    $log = AuditLog::where('entity', 'sale')->where('action', 'reversed')->sole();
    expect($log->user_id)->toBe($w->manager->id)
        ->and($log->after_json['reason'])->toBe('Duplicate');

    AuditLog::where('entity', 'user')->get()->each(
        fn ($entry) => expect($entry->after_json)->not->toHaveKey('password')
    );
});

test('U4: only management can read the audit log', function () {
    $this->actingAs($this->manager)->get(route('audit.index'))->assertOk();
    $this->actingAs(User::factory()->warehouse()->create())->get(route('audit.index'))->assertForbidden();
});

test('every staff page renders', function () {
    $w = stockWorld();

    foreach (['stock.index', 'stock.movements', 'returns.index', 'sales.index', 'products.index', 'users.index', 'audit.index', 'profile.edit'] as $page) {
        $this->actingAs($w->manager)->get(route($page))->assertOk();
    }

    foreach (['member.home', 'member.sales'] as $page) {
        $this->actingAs($w->member)->get(route($page))->assertOk();
    }
});
