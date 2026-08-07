<?php

namespace Deaduseful\DomainApi\Tests\Helpers;

use Deaduseful\DomainApi\Service;

class DomainTestHelper
{
    /**
     * Create a service instance with a test domain
     */
    public static function createService(string $domain = 'example.com'): Service
    {
        $cache = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('get')->andReturn(null);
        $cache->shouldReceive('put')->andReturn(true);

        $config = \Mockery::mock(\Illuminate\Contracts\Config\Repository::class);

        return (new Service($cache, $config))->setQuery($domain);
    }

    /**
     * Get test domains for data providers
     */
    public static function getTestDomains(): array
    {
        return [
            'example.com',
            'test.co.uk',
            'sample.org',
            'demo.net',
        ];
    }

    /**
     * Get invalid domains for testing validation
     */
    public static function getInvalidDomains(): array
    {
        return [
            'invalid-domain',
            'not.a.valid.domain.name',
            '123.456.789',
            'domain with spaces.com',
        ];
    }

    /**
     * Mock successful availability response
     */
    public static function mockAvailabilityResponse(): array
    {
        return [
            'response' => 'Domain is available',
            'code' => 200,
            'status' => Service::STATUS_AVAILABLE,
        ];
    }

    /**
     * Mock taken domain response
     */
    public static function mockTakenResponse(): array
    {
        return [
            'response' => 'Domain is taken',
            'code' => 200,
            'status' => Service::STATUS_TAKEN,
        ];
    }

    /**
     * Mock whois response
     */
    public static function mockWhoisResponse(): array
    {
        return [
            'tld' => 'com',
            'server' => 'whois.verisign-grs.com',
            'result' => 'Domain Name: EXAMPLE.COM\nRegistry Domain ID: 1234567890\nRegistrar WHOIS Server: whois.example.com',
        ];
    }

    /**
     * Mock RDAP response (normalised + raw)
     */
    public static function mockRdapResponse(): array
    {
        return [
            'domain' => 'example.com',
            'tld' => 'com',
            'status' => 'active',
            'protocol' => 'rdap',
            'server' => 'https://rdap.verisign.com/com/v1/',
            'registrar' => 'Example Registrar, Inc.',
            'nameservers' => ['A.IANA-SERVERS.NET', 'B.IANA-SERVERS.NET'],
            'contacts' => [],
            'created_date' => '1995-08-14T04:00:00+00:00',
            'updated_date' => '2024-08-14T07:01:39+00:00',
            'expiry_date' => '2025-08-13T04:00:00+00:00',
            'data' => [
                'handle' => '2336799_DOMAIN_COM-VRSN',
                'ldh_name' => 'EXAMPLE.COM',
                'status' => ['clientDeleteProhibited', 'clientTransferProhibited'],
                'secure_dns' => ['delegationSigned' => false],
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '1995-08-14T04:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2025-08-13T04:00:00Z'],
                    ['eventAction' => 'last changed', 'eventDate' => '2024-08-14T07:01:39Z'],
                ],
            ],
            'raw' => [
                'objectClassName' => 'domain',
                'handle' => '2336799_DOMAIN_COM-VRSN',
                'ldhName' => 'EXAMPLE.COM',
                'status' => ['clientDeleteProhibited', 'clientTransferProhibited'],
                'nameservers' => [
                    ['objectClassName' => 'nameserver', 'ldhName' => 'A.IANA-SERVERS.NET'],
                    ['objectClassName' => 'nameserver', 'ldhName' => 'B.IANA-SERVERS.NET'],
                ],
                'secureDNS' => ['delegationSigned' => false],
                'entities' => [
                    [
                        'roles' => ['registrar'],
                        'vcardArray' => [
                            'vcard',
                            [
                                ['version', [], 'text', '4.0'],
                                ['fn', [], 'text', 'Example Registrar, Inc.'],
                            ],
                        ],
                    ],
                ],
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '1995-08-14T04:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2025-08-13T04:00:00Z'],
                    ['eventAction' => 'last changed', 'eventDate' => '2024-08-14T07:01:39Z'],
                ],
                'links' => [
                    [
                        'rel' => 'self',
                        'href' => 'https://rdap.verisign.com/com/v1/domain/EXAMPLE.COM',
                    ],
                ],
            ],
        ];
    }

    /**
     * Mock transferable response
     */
    public static function mockTransferableResponse(): array
    {
        return [
            'response' => 'Domain transfer check completed',
            'code' => 200,
            'status' => 'success',
            'attributes' => [
                'transferrable' => 1,
            ],
        ];
    }

    /**
     * Mock suggest response
     */
    public static function mockSuggestResponse(): array
    {
        return [
            'example.com' => ['available' => true, 'price' => '12.99'],
            'example.net' => ['available' => true, 'price' => '14.99'],
            'example.org' => ['available' => false, 'price' => null],
        ];
    }
}
