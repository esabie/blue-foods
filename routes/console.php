<?php

use App\Services\Shop\OnlineShop;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('shop:release-expired', function (OnlineShop $shop) {
    $this->info($shop->releaseExpired().' unpaid order(s) released.');
})->purpose('Cancel unpaid online orders whose 15-minute stock hold has passed');

Artisan::command('notifications:prune', function () {
    $deleted = DatabaseNotification::whereNotNull('read_at')->where('created_at', '<', now()->subDays(90))->delete();
    $this->info("{$deleted} old notification(s) deleted.");
})->purpose('Delete read notifications older than 90 days');

Schedule::command('shop:release-expired')->everyMinute()->withoutOverlapping();
Schedule::command('stock:check')->dailyAt('05:00');
Schedule::command('notifications:prune')->dailyAt('04:30');
