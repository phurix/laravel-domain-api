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
use Spatie\Rdap\CouldNotFindRdapServer;
use Spatie\Rdap\Rdap;
use Spatie\Rdap\RdapDns;
use Spatie\Rdap\Responses\DomainResponse;
use UnexpectedValueException;

class RdapServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rdap_returns_normalised_payload(): void
    {
        $fixture = DomainTestHelper::mockRdapResponse();
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->once();

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')
            ->with('example.com')
            ->andReturn('https://rdap.verisign.com/com/v1/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')
            ->once()
            ->withArgs(function (string $domain, $timeout = null, $retry = null, $sleep = null, $dnsServer = null) {
                return $domain === 'example.com'
                    && $dnsServer === 'https://rdap.verisign.com/com/v1/';
            })
            ->andReturn(new DomainResponse($fixture['raw']));

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());
        $result = $service->setQuery('example.com')->rdap();

        $this->assertSame('active', $result['status']);
        $this->assertSame('rdap', $result['protocol']);
        $this->assertArrayHasKey('raw', $result);
        $this->assertSame('Example Registrar, Inc.', $result['registrar']);
    }

    public function test_rdap_returns_available_when_domain_missing(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);
        $cache->shouldReceive('put')->once();

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn('https://rdap.example/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')->andReturn(null);

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());
        $result = $service->setQuery('missing.example')->rdap();

        $this->assertSame(RdapNormaliser::STATUS_AVAILABLE, $result['status']);
        $this->assertSame([], $result['raw']);
    }

    public function test_rdap_throws_when_server_missing(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn(null);

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());

        $this->expectException(DomainException::class);
        $service->setQuery('example.invalidtld')->rdap();
    }

    public function test_rdap_maps_could_not_find_server(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn('https://rdap.example/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')->andThrow(CouldNotFindRdapServer::forDomain('example.com'));

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());

        $this->expectException(DomainException::class);
        $service->setQuery('example.com')->rdap();
    }

    public function test_rdap_maps_unexpected_failures(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->andReturn(false);

        $config = Mockery::mock(ConfigRepository::class);
        $dns = Mockery::mock(RdapDns::class);
        $dns->shouldReceive('getServerForDomain')->andReturn('https://rdap.example/');

        $rdap = Mockery::mock(Rdap::class);
        $rdap->shouldReceive('dns')->andReturn($dns);
        $rdap->shouldReceive('domain')->andThrow(\Spatie\Rdap\Exceptions\InvalidRdapResponse::make('example.com'));

        $service = new Service($cache, $config, $rdap, new RdapNormaliser());

        $this->expectException(UnexpectedValueException::class);
        $service->setQuery('example.com')->rdap();
    }
}
