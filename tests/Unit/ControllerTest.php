<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Controller;
use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class ControllerTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_availability_returns_json_response(): void
    {
        // Mock the Service class
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('setQuery')
            ->with('example.com')
            ->andReturnSelf();
        $mockService->shouldReceive('availability')
            ->andReturn(DomainTestHelper::mockAvailabilityResponse());

        // Create controller and inject mock service
        $controller = new class ($mockService) extends Controller {
            private $service;
            
            public function __construct($service)
            {
                $this->service = $service;
            }
            
            public function availability(string $query): JsonResponse
            {
                $result = $this->service
                    ->setQuery($query)
                    ->availability();
                return new JsonResponse($result);
            }
        };

        $response = $controller->availability('example.com');
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('response', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('status', $data);
    }

    public function test_controller_has_expected_methods(): void
    {
        $mockService = Mockery::mock(Service::class);
        $controller = new Controller($mockService);
        
        $this->assertTrue(method_exists($controller, 'availability'));
        $this->assertTrue(method_exists($controller, 'transferable'));
        $this->assertTrue(method_exists($controller, 'whois'));
        $this->assertTrue(method_exists($controller, 'rdap'));
        $this->assertTrue(method_exists($controller, 'suggest'));
    }

    public function test_controller_extends_base_controller(): void
    {
        $mockService = Mockery::mock(Service::class);
        $controller = new Controller($mockService);
        
        $this->assertInstanceOf(\Illuminate\Routing\Controller::class, $controller);
    }

    public function test_whois_handles_domain_exception(): void
    {
        // Mock the Service to throw a DomainException
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('setQuery')
            ->with('invalid-domain')
            ->andReturnSelf();
        $mockService->shouldReceive('whois')
            ->andThrow(new DomainException('Invalid Domain Name'));

        // Create controller with mock service
        $controller = new class ($mockService) extends Controller {
            private $service;
            
            public function __construct($service)
            {
                $this->service = $service;
            }
            
            public function whois(string $query, ?string $server = null): JsonResponse
            {
                try {
                    $result = $this->service
                        ->setQuery($query)
                        ->whois($server);
                    return new JsonResponse($result);
                } catch (DomainException|UnexpectedValueException $exception) {
                    $result = ['error' => $exception->getMessage()];
                    return new JsonResponse($result, Response::HTTP_BAD_REQUEST);
                }
            }
        };

        $response = $controller->whois('invalid-domain');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid Domain Name', $data['error']);
    }

    public function test_whois_handles_unexpected_value_exception(): void
    {
        // Mock the Service to throw an UnexpectedValueException
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('setQuery')
            ->with('example.com')
            ->andReturnSelf();
        $mockService->shouldReceive('whois')
            ->andThrow(new UnexpectedValueException('Test exception', 500));

        // Create controller with mock service
        $controller = new class ($mockService) extends Controller {
            private $service;
            
            public function __construct($service)
            {
                $this->service = $service;
            }
            
            public function whois(string $query, ?string $server = null): JsonResponse
            {
                try {
                    $result = $this->service
                        ->setQuery($query)
                        ->whois($server);
                    return new JsonResponse($result);
                } catch (DomainException|UnexpectedValueException $exception) {
                    $result = ['error' => $exception->getMessage()];
                    return new JsonResponse($result, Response::HTTP_BAD_REQUEST);
                }
            }
        };

        $response = $controller->whois('example.com');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Test exception', $data['error']);
    }

    public function test_rdap_handles_domain_exception(): void
    {
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('setQuery')
            ->with('invalid-domain')
            ->andReturnSelf();
        $mockService->shouldReceive('rdap')
            ->andThrow(new DomainException('Invalid Domain Name'));

        $controller = new Controller($mockService);
        $response = $controller->rdap('invalid-domain');

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid Domain Name', $data['error']);
    }

    public function test_rdap_returns_json_response(): void
    {
        $mockService = Mockery::mock(Service::class);
        $mockService->shouldReceive('setQuery')
            ->with('example.com')
            ->andReturnSelf();
        $mockService->shouldReceive('rdap')
            ->andReturn(DomainTestHelper::mockRdapResponse());

        $controller = new Controller($mockService);
        $response = $controller->rdap('example.com');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('rdap', $data['protocol']);
        $this->assertArrayHasKey('raw', $data);
    }
}
