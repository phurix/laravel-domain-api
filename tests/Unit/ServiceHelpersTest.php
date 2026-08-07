<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\Tests\Helpers\DomainTestHelper;
use DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class ServiceHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(?CacheRepository $cache = null): Service
    {
        return new class(
            $cache ?? Mockery::mock(CacheRepository::class),
            Mockery::mock(ConfigRepository::class)
        ) extends Service {
            public function callValidateDomain(string $query): void
            {
                $this->validateDomain($query);
            }

            public function callParseWhoisServer(string $result): string
            {
                return $this->parseWhoisServer($result);
            }

            public function callCheckCacheResult(string $key)
            {
                return $this->checkCacheResult($key);
            }

            public function callSaveCacheResult($result, string $key): void
            {
                $this->saveCacheResult($result, $key);
            }
        };
    }

    public function test_validate_domain_accepts_valid_hostname(): void
    {
        $service = $this->makeService();
        $service->callValidateDomain('example.com');
        $this->assertTrue(true);
    }

    public function test_validate_domain_rejects_invalid_hostname(): void
    {
        $service = $this->makeService();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid Domain Name');

        $service->callValidateDomain('domain with spaces.com');
    }

    public function test_parse_whois_server_extracts_whois_line(): void
    {
        $service = $this->makeService();
        $iana = DomainTestHelper::mockIanaWhoisResponse('whois.verisign-grs.com');

        $this->assertSame('whois.verisign-grs.com', $service->callParseWhoisServer($iana));
    }

    public function test_parse_whois_server_returns_empty_when_missing(): void
    {
        $service = $this->makeService();
        $text = "domain: COM\nstatus: ACTIVE\n";

        $this->assertSame('', $service->callParseWhoisServer($text));
    }

    public function test_check_cache_result_returns_hit(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->with('cache-key')->andReturn(true);
        $cache->shouldReceive('get')->once()->with('cache-key')->andReturn(['cached' => true]);

        $service = $this->makeService($cache);

        $this->assertSame(['cached' => true], $service->callCheckCacheResult('cache-key'));
    }

    public function test_check_cache_result_returns_null_on_miss(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('has')->once()->with('cache-key')->andReturn(false);
        $cache->shouldReceive('get')->never();

        $service = $this->makeService($cache);

        $this->assertNull($service->callCheckCacheResult('cache-key'));
    }

    public function test_save_cache_result_puts_non_empty_payload(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('put')
            ->once()
            ->with('cache-key', ['ok' => true], Mockery::type('int'))
            ->andReturn(true);

        $service = $this->makeService($cache);
        $service->callSaveCacheResult(['ok' => true], 'cache-key');

        $this->addToAssertionCount(1);
    }

    public function test_save_cache_result_skips_empty_payload(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('put')->never();

        $service = $this->makeService($cache);
        $service->callSaveCacheResult([], 'cache-key');
        $service->callSaveCacheResult(null, 'cache-key');

        $this->addToAssertionCount(1);
    }
}
