<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Facade;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every HTTP request starts like a request on a real server: scoped container instances and cached
     * facade roots are dropped first (what Octane does between requests). Without this, a test that makes
     * several requests keeps Filament's per-request state (current panel, original request) from the
     * previous one, which older Filament versions do not reset.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();
        Facade::clearResolvedInstances();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
