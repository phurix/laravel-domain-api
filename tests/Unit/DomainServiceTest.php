<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use DomainException;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use UnexpectedValueException;

class DomainServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

    public function test_is_available_returns_true_for_available_domain(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('availability')
            ->andReturn(DomainTestHelper::mockAvailabilityResponse());

        $result = $service->isAvailable();
        $this->assertTrue($result);
    }

    public function test_is_available_returns_false_for_taken_domain(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('availability')
            ->andReturn(DomainTestHelper::mockTakenResponse());

        $result = $service->isAvailable();
        $this->assertFalse($result);
    }

    public function test_is_available_returns_null_for_unknown_status(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('availability')
            ->andReturn([
                'response' => 'Unknown status',
                'code' => 200,
                'status' => 'unknown',
            ]);

        $result = $service->isAvailable();
        $this->assertNull($result);
    }

    public function test_is_available_throws_exception_when_status_empty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API Error');

        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('availability')
            ->andReturn([
                'response' => 'API Error',
                'code' => 500,
                'status' => '',
            ]);

        $service->isAvailable();
    }

    public function test_can_transfer_returns_true_for_transferable_domain(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('transferable')
            ->andReturn(DomainTestHelper::mockTransferableResponse());

        $result = $service->canTransfer();
        $this->assertTrue($result);
    }

    public function test_can_transfer_returns_false_for_non_transferable_domain(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('transferable')
            ->andReturn([
                'response' => 'Domain transfer check completed',
                'code' => 200,
                'status' => 'success',
                'attributes' => [
                    'transferrable' => 0,
                ],
            ]);

        $result = $service->canTransfer();
        $this->assertFalse($result);
    }

    public function test_can_transfer_returns_null_for_unknown_transfer_status(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('transferable')
            ->andReturn([
                'response' => 'Domain transfer check completed',
                'code' => 200,
                'status' => 'success',
                'attributes' => [
                    'transferrable' => 2, // Unknown status
                ],
            ]);

        $result = $service->canTransfer();
        $this->assertNull($result);
    }

    public function test_can_transfer_throws_exception_when_attributes_empty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API Error');

        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('transferable')
            ->andReturn([
                'response' => 'API Error',
                'code' => 500,
                'status' => 'error',
            ]);

        $service->canTransfer();
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
}
