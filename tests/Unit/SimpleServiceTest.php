<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Service;
use Mockery;
use PHPUnit\Framework\TestCase;

class SimpleServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    public function test_service_constants_are_defined(): void
    {
        $this->assertIsArray(Service::TLDS);
        $this->assertIsArray(Service::SUGGEST_SERVICES);
        $this->assertEquals('available', Service::STATUS_AVAILABLE);
        $this->assertEquals('taken', Service::STATUS_TAKEN);
    }

    public function test_service_has_expected_methods(): void
    {
        $mockCache = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $mockConfig = Mockery::mock('Illuminate\Contracts\Config\Repository');
        $service = new Service($mockCache, $mockConfig);
        
        $this->assertTrue(method_exists($service, 'setQuery'));
        $this->assertTrue(method_exists($service, 'availability'));
        $this->assertTrue(method_exists($service, 'transferable'));
        $this->assertTrue(method_exists($service, 'whois'));
        $this->assertTrue(method_exists($service, 'suggest'));
        $this->assertTrue(method_exists($service, 'isAvailable'));
        $this->assertTrue(method_exists($service, 'canTransfer'));
    }

    public function test_set_query_returns_service_instance(): void
    {
        $mockCache = Mockery::mock('Illuminate\Contracts\Cache\Repository');
        $mockConfig = Mockery::mock('Illuminate\Contracts\Config\Repository');
        $service = new Service($mockCache, $mockConfig);
        $result = $service->setQuery('example.com');
        
        $this->assertInstanceOf(Service::class, $result);
        $this->assertSame($service, $result);
    }

    public function test_tlds_constant_contains_expected_values(): void
    {
        $expectedTlds = ['com', 'co.uk', 'eu', 'co', 'biz', 'cc', 'club', 'info', 'name', 'net', 'org', 'tv', 'uk', 'org.uk', 'me.uk', 'us', 'ws'];
        
        foreach ($expectedTlds as $tld) {
            $this->assertContains($tld, Service::TLDS);
        }
    }

    public function test_suggest_services_constant_contains_expected_values(): void
    {
        $expectedServices = ['lookup', 'suggestion', 'premium', 'personal_names'];
        
        foreach ($expectedServices as $service) {
            $this->assertContains($service, Service::SUGGEST_SERVICES);
        }
    }
}
