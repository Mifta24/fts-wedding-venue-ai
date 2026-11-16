<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No live exchange-rate calls from tests; conversion tests turn the feed on and fake it.
        config(['services.fx.feed' => null, 'services.fx.fallback_feed' => null]);
    }
}
