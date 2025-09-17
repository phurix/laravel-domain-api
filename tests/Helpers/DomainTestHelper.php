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
        return (new Service())->setQuery($domain);
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
