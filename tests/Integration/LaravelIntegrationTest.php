<?php

namespace Deaduseful\DomainApi\Tests\Integration;

use Deaduseful\DomainApi\Service;
use Mockery;
use PHPUnit\Framework\TestCase;

class LaravelIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    public function test_service_can_be_instantiated(): void
    {
        $mockCache = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $mockConfig = Mockery::mock('Illuminate\Contracts\Config\Repository');
        $service = new Service($mockCache, $mockConfig);
        $this->assertInstanceOf(Service::class, $service);
    }

    public function test_service_set_query_works(): void
    {
        $mockCache = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $mockConfig = Mockery::mock('Illuminate\Contracts\Config\Repository');
        $service = new Service($mockCache, $mockConfig);
        $result = $service->setQuery('example.com');
        
        $this->assertInstanceOf(Service::class, $result);
        $this->assertSame($service, $result);
    }

    public function test_service_has_required_constants(): void
    {
        $this->assertIsArray(Service::TLDS);
        $this->assertIsArray(Service::SUGGEST_SERVICES);
        $this->assertEquals('available', Service::STATUS_AVAILABLE);
        $this->assertEquals('taken', Service::STATUS_TAKEN);
    }
}
