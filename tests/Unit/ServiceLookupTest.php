<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\ServiceProvider;
use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use Deaduseful\Opensrs\FastLookup;
use Deaduseful\Opensrs\Lookup;
use Deaduseful\Whois\Lookup as WhoisLookup;
use DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class ServiceLookupTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_availability_returns_result_and_caches(): void
    {
        $payload = DomainTestHelper::mockAvailabilityResponse();
        $payload['code'] = 210;

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);
        $cache->shouldReceive('put')->once()->with(Mockery::type('string'), $payload, Mockery::type('int'));

        $fastLookup = Mockery::mock(FastLookup::class);
        $fastLookup->shouldReceive('lookup')->once()->with('example.com')->andReturn($payload);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            $fastLookup
        );

        $result = $service->setQuery('example.com')->availability();

        $this->assertSame($payload, $result);
    }

    public function test_availability_returns_cache_hit_without_lookup(): void
    {
        $payload = DomainTestHelper::mockAvailabilityResponse();
        $payload['code'] = 210;

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(true);
        $cache->shouldReceive('get')->once()->andReturn($payload);
        $cache->shouldReceive('put')->never();

        $fastLookup = Mockery::mock(FastLookup::class);
        $fastLookup->shouldReceive('lookup')->never();

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            $fastLookup
        );

        $this->assertSame($payload, $service->setQuery('example.com')->availability());
    }

    public function test_availability_rejects_invalid_domain(): void
    {
        $service = new Service(
            Mockery::mock(CacheRepository::class),
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            Mockery::mock(FastLookup::class)
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid Domain Name');

        $service->setQuery('domain with spaces.com')->availability();
    }

    public function test_availability_throws_on_non_success_code(): void
    {
        $payload = [
            'response' => 'Unknown TLD',
            'code' => 701,
            'status' => 'unknown_tld',
        ];

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);
        $cache->shouldReceive('put')->never();

        $fastLookup = Mockery::mock(FastLookup::class);
        $fastLookup->shouldReceive('lookup')->once()->andReturn($payload);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            $fastLookup
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown TLD');

        $service->setQuery('example.com')->availability();
    }

    public function test_transferable_uses_injected_opensrs_lookup(): void
    {
        $payload = DomainTestHelper::mockTransferableResponse();

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);
        $cache->shouldReceive('put')->once()->with(Mockery::type('string'), $payload, Mockery::type('int'));

        $opensrs = Mockery::mock(Lookup::class);
        $opensrs->shouldReceive('lookup')
            ->once()
            ->with('example.com', 'check_transfer')
            ->andReturn($payload);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            $opensrs
        );

        $this->assertSame($payload, $service->setQuery('example.com')->transferable());
    }

    public function test_transferable_returns_cache_hit(): void
    {
        $payload = DomainTestHelper::mockTransferableResponse();

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(true);
        $cache->shouldReceive('get')->once()->andReturn($payload);

        $opensrs = Mockery::mock(Lookup::class);
        $opensrs->shouldReceive('lookup')->never();

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            $opensrs
        );

        $this->assertSame($payload, $service->setQuery('example.com')->transferable());
    }

    public function test_suggest_returns_attributes(): void
    {
        $envelope = DomainTestHelper::mockSuggestEnvelope();

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);
        $cache->shouldReceive('put')->once()->with(Mockery::type('string'), $envelope, Mockery::type('int'));

        $opensrs = Mockery::mock(Lookup::class);
        $opensrs->shouldReceive('suggest')
            ->once()
            ->with('example', ['com'], ['lookup'])
            ->andReturn($envelope);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            $opensrs
        );

        $result = $service->setQuery('example')->suggest(['com'], ['lookup']);

        $this->assertSame($envelope['attributes'], $result);
    }

    public function test_suggest_throws_when_attributes_empty(): void
    {
        $envelope = [
            'response' => 'Suggest failed',
            'code' => 500,
            'status' => 'error',
            'attributes' => [],
        ];

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);
        $cache->shouldReceive('put')->never();

        $opensrs = Mockery::mock(Lookup::class);
        $opensrs->shouldReceive('suggest')->once()->andReturn($envelope);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            $opensrs
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Suggest failed');

        $service->setQuery('example')->suggest(['com'], ['lookup']);
    }

    public function test_suggest_throws_when_opensrs_config_missing(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);

        $config = Mockery::mock(ConfigRepository::class);
        $config->shouldReceive('get')
            ->once()
            ->with(ServiceProvider::NAMESPACE . '.opensrs')
            ->andReturn(null);

        $service = new Service($cache, $config);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Missing Config');

        $service->setQuery('example')->suggest(['com'], ['lookup']);
    }

    public function test_whois_with_explicit_server_uses_factory(): void
    {
        $whoisText = "Domain Name: EXAMPLE.COM\nRegistrar: Example";
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);
        $cache->shouldReceive('put')->once();

        $whoisLookup = Mockery::mock(WhoisLookup::class);
        $whoisLookup->shouldReceive('query')
            ->once()
            ->with('example.com')
            ->andReturn($whoisText);

        $factoryCalls = [];
        $factory = function (string $query, string $host, ?int $port) use (&$factoryCalls, $whoisLookup) {
            $factoryCalls[] = compact('query', 'host', 'port');
            return $whoisLookup;
        };

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            null,
            $factory
        );

        $result = $service->setQuery('example.com')->whois('whois.verisign-grs.com');

        $this->assertSame('com', $result['tld']);
        $this->assertSame('whois.verisign-grs.com', $result['server']);
        $this->assertSame($whoisText, $result['result']);
        $this->assertCount(1, $factoryCalls);
        $this->assertSame('whois.verisign-grs.com', $factoryCalls[0]['host']);
        $this->assertNull($factoryCalls[0]['port']);
    }

    public function test_whois_discovers_server_via_iana(): void
    {
        $ianaText = DomainTestHelper::mockIanaWhoisResponse('whois.verisign-grs.com');
        $whoisText = "Domain Name: EXAMPLE.COM";

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->twice();

        $ianaLookup = Mockery::mock(WhoisLookup::class);
        $ianaLookup->shouldReceive('query')->once()->with('com')->andReturn($ianaText);

        $domainLookup = Mockery::mock(WhoisLookup::class);
        $domainLookup->shouldReceive('query')->once()->with('example.com')->andReturn($whoisText);

        $factory = function (string $query, string $host, ?int $port) use ($ianaLookup, $domainLookup) {
            if ($host === 'whois.iana.org') {
                return $ianaLookup;
            }
            if ($host === 'whois.verisign-grs.com') {
                return $domainLookup;
            }
            $this->fail('Unexpected whois host: ' . $host);
        };

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            null,
            $factory
        );

        $result = $service->setQuery('example.com')->whois();

        $this->assertSame('whois.verisign-grs.com', $result['server']);
        $this->assertSame($whoisText, $result['result']);
    }

    public function test_whois_throws_when_server_empty(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            null,
            function () {
                $this->fail('Factory should not be called when server is empty');
            }
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unable to find whois server for com');

        $service->setQuery('example.com')->whois('');
    }

    public function test_whois_returns_cache_hit(): void
    {
        $payload = DomainTestHelper::mockWhoisResponse();

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(true);
        $cache->shouldReceive('get')->once()->andReturn($payload);

        $service = new Service(
            $cache,
            Mockery::mock(ConfigRepository::class),
            null,
            null,
            null,
            null,
            function () {
                $this->fail('Factory should not be called on cache hit');
            }
        );

        $this->assertSame($payload, $service->setQuery('example.com')->whois('whois.verisign-grs.com'));
    }

    public function test_set_test_selects_test_config_mode(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->andReturn(false);

        $config = Mockery::mock(ConfigRepository::class);
        $config->shouldReceive('get')
            ->once()
            ->with(ServiceProvider::NAMESPACE . '.opensrs')
            ->andReturn([
                'live' => ['username' => 'live-user', 'key' => 'live-key'],
                'test' => null,
            ]);

        $service = new Service($cache, $config);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Missing "test" Config');

        $service->setQuery('example.com')->setTest(true)->transferable();
    }
}
