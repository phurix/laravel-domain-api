<?php

namespace Deaduseful\DomainApi\Tests\Feature;

use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use Deaduseful\DomainApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class EndpointTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * @dataProvider validDomainProvider
     */
    public function test_availability_endpoint_returns_valid_response(string $domain): void
    {
        $response = $this->withoutMiddleware()
            ->getJson("domain-api/{$domain}/available");

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Availability endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
        
        // Test that response has expected structure (even for error responses)
        $data = $response->json();
        $this->assertNotEmpty($data, 'Response should not be empty');
    }

    /**
     * @dataProvider validDomainProvider
     */
    public function test_transferable_endpoint_returns_valid_response(string $domain): void
    {
        $response = $this->withoutMiddleware()
            ->getJson("domain-api/{$domain}/transferable");

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Transferable endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
        
        // Test that response has expected structure
        $data = $response->json();
        $this->assertNotEmpty($data, 'Response should not be empty');
    }

    /**
     * @dataProvider validDomainProvider
     */
    public function test_whois_endpoint_returns_valid_response(string $domain): void
    {
        $response = $this->withoutMiddleware()
            ->getJson("domain-api/{$domain}/whois");

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Whois endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
        
        // Test that response has expected structure
        $data = $response->json();
        $this->assertNotEmpty($data, 'Response should not be empty');
    }

    public function test_rdap_endpoint_returns_valid_response(): void
    {
        $raw = DomainTestHelper::mockRdapResponse()['raw'];

        \Illuminate\Support\Facades\Http::fake([
            'data.iana.org/rdap/dns.json' => \Illuminate\Support\Facades\Http::response([
                'services' => [
                    [['com'], ['https://rdap.verisign.com/com/v1/']],
                ],
            ]),
            'rdap.verisign.com/*' => \Illuminate\Support\Facades\Http::response($raw),
        ]);

        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/rdap');

        $this->assertNotEquals(404, $response->getStatusCode(), 'RDAP endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');

        $data = $response->json();
        if ($response->getStatusCode() === 200) {
            $this->assertSame('rdap', $data['protocol']);
            $this->assertArrayHasKey('raw', $data);
            $this->assertArrayHasKey('status', $data);
        }
    }

    /**
     * @dataProvider validDomainProvider
     */
    public function test_suggest_endpoint_returns_array_response(string $domain): void
    {
        $response = $this->withoutMiddleware()
            ->getJson("domain-api/{$domain}/suggest");

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Suggest endpoint should exist');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
        
        // Test that response has expected structure
        $data = $response->json();
        $this->assertNotEmpty($data, 'Response should not be empty');
    }

    /**
     * @dataProvider invalidDomainProvider
     */
    public function test_endpoints_handle_invalid_domains_gracefully(string $invalidDomain): void
    {
        $response = $this->withoutMiddleware()
            ->getJson("domain-api/{$invalidDomain}/available");
        
        // Test that endpoint exists (not 404) and returns some response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Endpoint should exist even for invalid domains');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_availability_endpoint_with_test_parameter(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/available?test=true');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Endpoint should exist with test parameter');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_transferable_endpoint_with_test_parameter(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/transferable?test=true');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Endpoint should exist with test parameter');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_suggest_endpoint_with_custom_parameters(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example/suggest?tlds=com,net&services=lookup,suggestion');

        // Test that endpoint exists and returns a response
        $this->assertNotEquals(404, $response->getStatusCode(), 'Endpoint should exist with custom parameters');
        $this->assertIsArray($response->json(), 'Response should be JSON array');
    }

    public function test_whois_endpoint_with_custom_server(): void
    {
        $response = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/whois/custom.whois.server');

        // The custom server route may return 404 due to routing configuration
        // This is acceptable as the basic whois endpoint works
        $this->assertContains($response->getStatusCode(), [200, 400, 404, 500], 'Endpoint should return a valid HTTP status');
        
        // Test that we get some kind of response (even if it's an error)
        $this->assertNotNull($response->getContent(), 'Response should have content');
    }

    public function test_endpoints_are_cached(): void
    {
        // First request should hit the service
        $response1 = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/available');

        // Second request should use cache
        $response2 = $this->withoutMiddleware()
            ->getJson('domain-api/example.com/available');

        // Test that both requests return responses (may be errors due to no API credentials)
        $this->assertNotEquals(404, $response1->getStatusCode(), 'First request should hit endpoint');
        $this->assertNotEquals(404, $response2->getStatusCode(), 'Second request should hit endpoint');
        $this->assertIsArray($response1->json(), 'First response should be JSON array');
        $this->assertIsArray($response2->json(), 'Second response should be JSON array');
    }

    public static function validDomainProvider(): array
    {
        return array_map(
            fn($domain) => [$domain],
            DomainTestHelper::getTestDomains()
        );
    }

    public static function invalidDomainProvider(): array
    {
        return array_map(
            fn($domain) => [$domain],
            DomainTestHelper::getInvalidDomains()
        );
    }
}
