<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Rdap\RdapNormaliser;
use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use PHPUnit\Framework\TestCase;
use Spatie\Rdap\Responses\DomainResponse;

class RdapNormaliserTest extends TestCase
{
    private RdapNormaliser $normaliser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normaliser = new RdapNormaliser();
    }

    public function test_normalises_registered_domain_with_nested_raw(): void
    {
        $fixture = DomainTestHelper::mockRdapResponse();
        $response = new DomainResponse($fixture['raw']);

        $result = $this->normaliser->normalise(
            'example.com',
            'https://rdap.verisign.com/com/v1/',
            $response
        );

        $this->assertSame('example.com', $result['domain']);
        $this->assertSame('com', $result['tld']);
        $this->assertSame(RdapNormaliser::STATUS_ACTIVE, $result['status']);
        $this->assertSame('rdap', $result['protocol']);
        $this->assertSame('https://rdap.verisign.com/com/v1/', $result['server']);
        $this->assertSame('Example Registrar, Inc.', $result['registrar']);
        $this->assertSame(['A.IANA-SERVERS.NET', 'B.IANA-SERVERS.NET'], $result['nameservers']);
        $this->assertNotNull($result['created_date']);
        $this->assertNotNull($result['expiry_date']);
        $this->assertSame('2336799_DOMAIN_COM-VRSN', $result['data']['handle']);
        $this->assertSame($fixture['raw'], $result['raw']);
    }

    public function test_available_payload_for_unregistered_domain(): void
    {
        $result = $this->normaliser->availablePayload('available-example.test', 'https://rdap.example/');

        $this->assertSame(RdapNormaliser::STATUS_AVAILABLE, $result['status']);
        $this->assertSame([], $result['raw']);
        $this->assertSame([], $result['nameservers']);
        $this->assertNull($result['registrar']);
    }

    public function test_determine_status_expired(): void
    {
        $this->assertSame(
            RdapNormaliser::STATUS_EXPIRED,
            $this->normaliser->determineStatus(['status' => ['redemptionPeriod']])
        );
    }

    public function test_extract_tld(): void
    {
        $this->assertSame('com', $this->normaliser->extractTld('Example.COM'));
        $this->assertSame('uk', $this->normaliser->extractTld('example.co.uk'));
    }
}
