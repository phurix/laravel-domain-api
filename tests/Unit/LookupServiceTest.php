<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Rdap\RdapNormaliser;
use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Mockery;
use PHPUnit\Framework\TestCase;
use Spatie\Rdap\Exceptions\InvalidRdapResponse;
use Spatie\Rdap\Rdap;
use Spatie\Rdap\RdapDns;
use Spatie\Rdap\Responses\DomainResponse;

class LookupServiceTest extends TestCase
{
    private const LOOKUP_KEYS = [
        'domain',
        'tld',
        'status',
        'protocol',
        'server',
        'registrar',
        'nameservers',
        'contacts',
        'created_date',
        'updated_date',
        'expiry_date',
        'data',
        'raw',
        'fallback',
    ];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lookup_returns_rdap_payload_with_fallback_false(): void
    {
        $fixture = DomainTestHelper::mockRdapResponse();
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->twice();

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')
            ->with('example.com')
            ->andReturn('https://rdap.verisign.com/com/v1/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')
            ->once()
            ->andReturn(new DomainResponse($fixture['raw']));

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());
        $result = $service->setQuery('example.com')->lookup();

        $this->assertSame('rdap', $result['protocol']);
        $this->assertFalse($result['fallback']);
        $this->assertSame('active', $result['status']);
        $this->assertLookupContractKeys($result);
    }

    public function test_lookup_returns_available_from_rdap_without_whois(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->twice();

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn('https://rdap.example/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')->once()->andReturn(null);

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());
        $result = $service->setQuery('missing.example')->lookup();

        $this->assertSame(RdapNormaliser::STATUS_AVAILABLE, $result['status']);
        $this->assertSame('rdap', $result['protocol']);
        $this->assertFalse($result['fallback']);
        $this->assertLookupContractKeys($result);
    }

    public function test_lookup_falls_back_to_whois_when_rdap_server_missing(): void
    {
        $whois = DomainTestHelper::mockWhoisResponse();
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->once();

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn(null);

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);

        $service = new class($cache, $config, $rdap, new RdapNormaliser(), $whois) extends Service {
            private array $whoisFixture;

            public function __construct($cache, $config, $rdap, $normaliser, array $whoisFixture)
            {
                parent::__construct($cache, $config, $rdap, $normaliser);
                $this->whoisFixture = $whoisFixture;
            }

            public function whois(string $server = null): array
            {
                return $this->whoisFixture;
            }
        };

        $result = $service->setQuery('example.com')->lookup();

        $this->assertSame('whois', $result['protocol']);
        $this->assertTrue($result['fallback']);
        $this->assertSame(RdapNormaliser::STATUS_UNKNOWN, $result['status']);
        $this->assertSame($whois['result'], $result['data']['whois_text']);
        $this->assertSame($whois['result'], $result['raw']['whois']);
        $this->assertSame($whois['server'], $result['server']);
        $this->assertLookupContractKeys($result);
    }

    public function test_lookup_falls_back_to_whois_on_rdap_unexpected_failure(): void
    {
        $whois = DomainTestHelper::mockWhoisResponse();
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->once();

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn('https://rdap.example/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')->andThrow(InvalidRdapResponse::make('example.com'));

        $service = new class($cache, $config, $rdap, new RdapNormaliser(), $whois) extends Service {
            private array $whoisFixture;

            public function __construct($cache, $config, $rdap, $normaliser, array $whoisFixture)
            {
                parent::__construct($cache, $config, $rdap, $normaliser);
                $this->whoisFixture = $whoisFixture;
            }

            public function whois(string $server = null): array
            {
                return $this->whoisFixture;
            }
        };

        $result = $service->setQuery('example.com')->lookup();

        $this->assertSame('whois', $result['protocol']);
        $this->assertTrue($result['fallback']);
        $this->assertLookupContractKeys($result);
    }

    public function test_lookup_rethrows_when_whois_also_fails(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn(null);

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);

        $service = new class($cache, $config, $rdap, new RdapNormaliser()) extends Service {
            public function whois(string $server = null): array
            {
                throw new DomainException('Unable to find whois server for com');
            }
        };

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unable to find whois server for com');
        $service->setQuery('example.com')->lookup();
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertLookupContractKeys(array $result): void
    {
        foreach (self::LOOKUP_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Missing lookup contract key: {$key}");
        }
    }
}
