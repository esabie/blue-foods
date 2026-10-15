<?php

use App\Services\Reports;

test('every dashboard and report renders with data', function () {
    $w = stockWorld();
    ledger()->produce($w->product, 50, $w->warehouse);
    ledger()->assign($w->product, $w->member, 10, $w->manager);
    ledger()->recordSale($w->member, $w->product, 3);
    ledger()->allocateOnline($w->product, 5, $w->manager);
    placeOnlineOrder([$w->product->id => 1], 'pay_on_delivery');

    $this->actingAs($w->manager);

    foreach (['dashboards.overview', 'dashboards.stock', 'dashboards.team', 'dashboards.sales'] as $name) {
        $this->get(route($name))->assertOk();
        $this->get(route($name, ['from' => '2020-01-01', 'to' => now()->toDateString(), 'products' => [$w->product->id]]))->assertOk();
    }

    foreach (array_keys(Reports::TYPES) as $type) {
        foreach (Reports::GROUPINGS as $grouping) {
            $this->get(route('reports.show', ['type' => $type, 'grouping' => $grouping]))->assertOk();
        }
        $this->get(route('reports.export', $type))->assertOk()->assertDownload();
    }

    $this->get(route('orders.index'))->assertOk();
    $this->get(route('website.edit'))->assertOk();
});
