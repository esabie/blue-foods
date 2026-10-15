<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardsController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\PaymentController;
use App\Http\Controllers\Site\ShopController;
use App\Http\Controllers\Site\SiteController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebsiteController;
use App\Services\Reports;
use Illuminate\Support\Facades\Route;

/*
| Public website and online shop (section 7)
*/
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/about', [SiteController::class, 'about'])->name('site.about');
Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/{product:sku}', [ShopController::class, 'show'])->name('shop.show');

Route::get('/cart', [CheckoutController::class, 'cart'])->name('cart.show');
Route::post('/cart', [ShopController::class, 'addToCart'])->name('cart.add');
Route::patch('/cart/{product}', [CheckoutController::class, 'updateCart'])->name('cart.update');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

Route::get('/order/{order:reference}', [PaymentController::class, 'order'])->name('shop.orders.show');
Route::post('/order/{order:reference}/pay', [PaymentController::class, 'pay'])->middleware('throttle:10,1')->name('shop.orders.pay');
Route::get('/payments/{payment:reference}/return', [PaymentController::class, 'returned'])->name('shop.payments.return');
Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->name('shop.payments.webhook');
Route::get('/payments/sandbox/{payment:reference}', [PaymentController::class, 'sandbox'])->name('shop.sandbox.show');
Route::post('/payments/sandbox/{payment:reference}', [PaymentController::class, 'sandboxComplete'])->name('shop.sandbox.complete');

/*
| Staff app
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');

    Route::middleware('role:management')->group(function () {
        Route::get('/dashboards/overview', [DashboardsController::class, 'overview'])->name('dashboards.overview');
        Route::get('/dashboards/team', [DashboardsController::class, 'team'])->name('dashboards.team');
        Route::get('/dashboards/sales', [DashboardsController::class, 'sales'])->name('dashboards.sales');

        $reportType = implode('|', array_keys(Reports::TYPES));
        Route::get('/reports/{type?}', [ReportController::class, 'show'])->where('type', $reportType)->name('reports.show');
        Route::get('/reports/{type}/export', [ReportController::class, 'export'])->where('type', $reportType)->name('reports.export');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/photo', [ProductController::class, 'photo'])->name('products.photo');
        Route::post('/products/{product}/price', [ProductController::class, 'changePrice'])->name('products.price');
        Route::post('/products/{product}/cost', [ProductController::class, 'changeCost'])->name('products.cost');
        Route::post('/products/{product}/retire', [ProductController::class, 'retire'])->name('products.retire');
        Route::post('/products/{product}/reactivate', [ProductController::class, 'reactivate'])->name('products.reactivate');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/sales/{sale}/reverse', [SaleController::class, 'reverse'])->name('sales.reverse');

        Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
        Route::post('/cash/hand-ins', [CashController::class, 'store'])->name('cash.store');
        Route::post('/cash/hand-ins/{handIn}/cancel', [CashController::class, 'cancel'])->name('cash.cancel');

        Route::post('/stock/allocate-online', [StockController::class, 'allocateOnline'])->name('stock.allocate-online');

        Route::post('/online-orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('/website', [WebsiteController::class, 'edit'])->name('website.edit');
        Route::post('/website', [WebsiteController::class, 'update'])->name('website.update');

        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');
    });

    Route::middleware('role:management,warehouse')->group(function () {
        Route::get('/dashboards/stock', [DashboardsController::class, 'stock'])->name('dashboards.stock');

        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/movements', [StockController::class, 'movements'])->name('stock.movements');
        Route::post('/stock/produce', [StockController::class, 'produce'])->name('stock.produce');
        Route::post('/stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::post('/stock/assign', [StockController::class, 'assign'])
            ->middleware('can:assign-stock')
            ->name('stock.assign');

        Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::post('/returns/{returnRequest}/confirm', [ReturnController::class, 'confirm'])->name('returns.confirm');
        Route::post('/returns/{returnRequest}/reject', [ReturnController::class, 'reject'])->name('returns.reject');

        Route::get('/online-orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/online-orders/{order}/pack', [OrderController::class, 'pack'])->name('orders.pack');
        Route::post('/online-orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    });

    Route::middleware('role:marketing')->prefix('my')->name('member.')->group(function () {
        Route::get('/stock', [MemberController::class, 'home'])->name('home');
        Route::get('/sales', [MemberController::class, 'sales'])->name('sales');
        Route::post('/sales', [MemberController::class, 'storeSale'])->name('sales.store');
        Route::post('/returns', [MemberController::class, 'storeReturn'])->name('returns.store');
    });
});

require __DIR__.'/auth.php';
