<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The stock_balances and effective_sales views must be dropped too when RefreshDatabase rebuilds the schema.
     */
    protected bool $dropViews = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
