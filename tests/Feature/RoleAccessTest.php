<?php

use App\Enums\HandInMethod;
use App\Models\User;
use App\Services\CashBook;
use Illuminate\Support\Facades\Route;

/**
 * U3: every endpoint, the roles allowed to use it, and how to build its URL.
 * Adding a route without listing it here fails the coverage test below.
 */
function endpointMatrix(): array
{
    $all = ['management', 'warehouse', 'marketing'];
    $mgmt = ['management'];
    $stock = ['management', 'warehouse'];
    $member = ['marketing'];

    return [
        ['GET', 'dashboard', [], $all],
        ['GET', 'profile.edit', [], $all],
        ['PUT', 'password.update', [], $all],
        ['GET', 'notifications.index', [], $all],
        ['POST', 'notifications.read', ['notification'], $all],
        ['POST', 'notifications.read-all', [], $all],

        ['GET', 'dashboards.overview', [], $mgmt],
        ['GET', 'dashboards.stock', [], $stock],
        ['GET', 'dashboards.team', [], $mgmt],
        ['GET', 'dashboards.sales', [], $mgmt],
        ['GET', 'reports.show', [], $mgmt],
        ['GET', 'reports.export', ['type'], $mgmt],

        ['GET', 'orders.index', [], $stock],
        ['POST', 'orders.pack', ['order'], $stock],
        ['POST', 'orders.complete', ['order'], $stock],
        ['POST', 'orders.cancel', ['order'], $mgmt],
        ['GET', 'website.edit', [], $mgmt],
        ['POST', 'website.update', [], $mgmt],

        ['GET', 'products.index', [], $mgmt],
        ['POST', 'products.store', [], $mgmt],
        ['PUT', 'products.update', ['product'], $mgmt],
        ['POST', 'products.photo', ['product'], $mgmt],
        ['POST', 'products.price', ['product'], $mgmt],
        ['POST', 'products.cost', ['product'], $mgmt],
        ['POST', 'products.retire', ['product'], $mgmt],
        ['POST', 'products.reactivate', ['product'], $mgmt],

        ['GET', 'users.index', [], $mgmt],
        ['POST', 'users.store', [], $mgmt],
        ['PUT', 'users.update', ['user'], $mgmt],
        ['POST', 'users.disable', ['user'], $mgmt],
        ['POST', 'users.enable', ['user'], $mgmt],
        ['POST', 'users.reset-password', ['user'], $mgmt],

        ['GET', 'sales.index', [], $mgmt],
        ['POST', 'sales.reverse', ['sale'], $mgmt],
        ['GET', 'cash.index', [], $mgmt],
        ['POST', 'cash.store', [], $mgmt],
        ['POST', 'cash.cancel', ['handIn'], $mgmt],
        ['POST', 'stock.allocate-online', [], $mgmt],
        ['GET', 'audit.index', [], $mgmt],

        ['GET', 'stock.index', [], $stock],
        ['GET', 'stock.movements', [], $stock],
        ['POST', 'stock.produce', [], $stock],
        ['POST', 'stock.adjust', [], $stock],
        ['POST', 'stock.assign', [], $mgmt],
        ['GET', 'returns.index', [], $stock],
        ['POST', 'returns.confirm', ['returnRequest'], $stock],
        ['POST', 'returns.reject', ['returnRequest'], $stock],

        ['GET', 'member.home', [], $member],
        ['GET', 'member.sales', [], $member],
        ['POST', 'member.sales.store', [], $member],
        ['POST', 'member.returns.store', [], $member],
    ];
}

beforeEach(function () {
    $w = stockWorld();
    ledger()->produce($w->product, 10, $w->warehouse);
    ledger()->assign($w->product, $w->member, 5, $w->manager);
    $sale = ledger()->recordSale($w->member, $w->product, 1);
    $return = ledger()->requestReturn($w->member, $w->product, 1);
    ledger()->allocateOnline($w->product, 2, $w->manager);
    $order = placeOnlineOrder([$w->product->id => 1], 'pay_on_delivery');

    $this->params = [
        'product' => $w->product,
        'user' => User::factory()->warehouse()->create(),
        'sale' => $sale,
        'returnRequest' => $return,
        'order' => $order,
        'type' => 'sales-summary',
        'notification' => $w->manager->notifications()->first()->id,
        'handIn' => app(CashBook::class)->record($w->member, 100, HandInMethod::Cash, null, null, $w->manager),
    ];
    $this->users = [
        'management' => $w->manager,
        'warehouse' => $w->warehouse,
        'marketing' => $w->member,
    ];
});

test('every role is refused outside its permissions and allowed within them', function () {
    foreach (endpointMatrix() as [$method, $name, $paramKeys, $allowed]) {
        $url = route($name, array_map(fn ($key) => $this->params[$key], $paramKeys));

        foreach ($this->users as $role => $user) {
            $status = $this->actingAs($user)->call($method, $url)->getStatusCode();
            $expectAllowed = in_array($role, $allowed, true);

            expect($status === 403)->toBe(! $expectAllowed, "{$role} {$method} {$name} returned {$status}");
        }
    }
});

test('guests are sent to sign in for every staff endpoint', function () {
    foreach (endpointMatrix() as [$method, $name, $paramKeys]) {
        $url = route($name, array_map(fn ($key) => $this->params[$key], $paramKeys));

        $this->call($method, $url)->assertRedirect(route('login'));
    }
});

/**
 * The public website and shop (section 7): open to customers, no staff access.
 */
function publicRoutes(): array
{
    return [
        'login', 'logout', 'password.request', 'password.email', 'password.reset', 'password.store', 'storage.local', 'storage.local.upload', 'sanctum.csrf-cookie',
        'site.home', 'site.about', 'site.contact', 'site.sitemap',
        'shop.index', 'shop.show', 'cart.show', 'cart.add', 'cart.update', 'checkout.show', 'checkout.store',
        'shop.orders.show', 'shop.orders.pay', 'shop.payments.return', 'shop.payments.webhook',
        'shop.sandbox.show', 'shop.sandbox.complete',
    ];
}

test('public website pages need no sign-in', function () {
    foreach (['site.home', 'site.about', 'site.contact', 'shop.index', 'cart.show', 'site.sitemap'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('shop.show', $this->params['product']->sku))->assertOk();
});

test('every authenticated route is covered by the role matrix', function () {
    $covered = array_column(endpointMatrix(), 1);
    $public = publicRoutes();

    $missing = collect(Route::getRoutes()->getRoutesByName())
        ->keys()
        ->reject(fn ($name) => in_array($name, [...$covered, ...$public], true))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
