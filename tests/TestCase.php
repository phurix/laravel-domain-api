<?php

namespace Deaduseful\DomainApi\Tests;

use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Rdap\RdapServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Register the service provider for testing
        $this->app->register(RdapServiceProvider::class);
        $this->app->register(ServiceProvider::class);
        
        // Set up test environment
        $this->app['config']->set('cache.default', 'array');
        $this->app['config']->set('queue.default', 'sync');
        $this->app['config']->set('session.driver', 'array');
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        
        // Set up test configuration for domain API
        $this->app['config']->set('domainapi.opensrs.test', [
            'username' => 'test_username',
            'key' => 'test_key',
        ]);
        
        $this->app['config']->set('domainapi.opensrs.live', [
            'username' => 'live_username',
            'key' => 'live_key',
        ]);
    }
}
