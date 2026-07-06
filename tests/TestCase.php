<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run the DatabaseSeeder (roles + demo accounts) before each test.
     */
    protected $seed = true;
}
