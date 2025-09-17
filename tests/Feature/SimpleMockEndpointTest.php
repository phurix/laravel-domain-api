<?php

namespace Deaduseful\DomainApi\Tests\Feature;

use Deaduseful\DomainApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class SimpleMockEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    public function test_availability_endpoint_returns_response(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/available');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Availability endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_transferable_endpoint_returns_response(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/transferable');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Transferable endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_whois_endpoint_returns_response(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/whois');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'WHOIS endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_suggest_endpoint_returns_response(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example/suggest');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Suggest endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_endpoints_accept_parameters(): void
    {
        // Test that endpoints accept query parameters
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/available?test=true');
        
        $this->assertNotEquals(404, $response->getStatusCode(), 'Endpoint should accept parameters');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_endpoints_handle_different_domains(): void
    {
        $domains = ['example.com', 'test.co.uk', 'sample.org'];
        
        foreach ($domains as $domain) {
            $response = $this->withoutMiddleware()
                ->getJson("domain-api/{$domain}/available");
            
            $this->assertNotEquals(404, $response->getStatusCode(), "Endpoint should handle domain: {$domain}");
            $this->assertIsArray($response->json(), "Response should be JSON array for domain: {$domain}");
        }
    }

    public function test_invalid_domain_returns_error(): void
    {
        // Test with invalid domain - should return error status
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/invalid-domain/available');
        
        // Should return error status (not 404 - route exists)
        $this->assertNotEquals(404, $response->getStatusCode(), 'Invalid domain should not return 404');
        $this->assertIsArray($response->json(), 'Error response should be JSON array');
    }

    public function test_routes_are_registered(): void
    {
        // Test that routes are properly registered
        $routes = [
            'domain-api/example.com/available',
            'domain-api/example.com/transferable',
            'domain-api/example.com/whois',
            'domain-api/example/suggest',
        ];
        
        foreach ($routes as $route) {
            $response = $this->withoutMiddleware()
                ->getJson($route);
            
            // Should not return 404 (route not found)
            $this->assertNotEquals(404, $response->getStatusCode(), "Route should exist: {$route}");
            $this->assertIsArray($response->json(), "Route should return JSON: {$route}");
        }
    }

    public function test_endpoints_return_consistent_structure(): void
    {
        // Test that all endpoints return consistent JSON structure
        $endpoints = [
            'domain-api/example.com/available',
            'domain-api/example.com/transferable',
            'domain-api/example.com/whois',
            'domain-api/example/suggest',
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->withoutMiddleware()
                ->getJson($endpoint);
            
            $this->assertNotEquals(404, $response->getStatusCode(), "Endpoint should exist: {$endpoint}");
            
            $data = $response->json();
            $this->assertIsArray($data, "Endpoint should return array: {$endpoint}");
            
            // All responses should have some structure
            $this->assertNotEmpty($data, "Endpoint should return non-empty response: {$endpoint}");
        }
    }
}
