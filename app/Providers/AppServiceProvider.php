<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaystackGateway;
use App\Services\Payments\SandboxGateway;
use App\Services\Shop\Cart;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\MnotifySmsSender;
use App\Services\Sms\SmsSender;
use App\Services\StaffNotifier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => match (config('services.payments.driver')) {
            'paystack' => new PaystackGateway(
                (string) config('services.paystack.secret_key'),
                (string) config('services.paystack.base_url'),
            ),
            'sandbox' => $this->app->isProduction()
                ? throw new InvalidArgumentException('The sandbox payment driver cannot be used in production.')
                : new SandboxGateway,
            default => throw new InvalidArgumentException('Unknown payment driver.'),
        });

        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            'mnotify' => new MnotifySmsSender(
                (string) config('services.sms.mnotify.key'),
                (string) config('services.sms.mnotify.sender'),
                (string) config('services.sms.mnotify.base_url'),
            ),
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unknown SMS driver.'),
        });

        // One instance per request, so StaffNotifier::quietly() covers every service that sends.
        $this->app->scoped(StaffNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // W11: the live site is served only over HTTPS.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        View::composer('site.*', function ($view) {
            $cart = app(Cart::class);
            $view->with([
                'site' => SiteSetting::values(),
                'cartCount' => $cart->count(),
                'cartLines' => $cart->lines(),
                'cartSubtotal' => $cart->subtotal(),
            ]);
        });

        Gate::define('assign-stock', fn (User $user) => $user->hasRole(Role::Management)
            || (config('inventory.warehouse_can_assign') && $user->hasRole(Role::Warehouse)));
    }
}
