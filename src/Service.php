<?php

namespace Deaduseful\DomainApi;

use Deaduseful\DomainApi\Rdap\RdapNormaliser;
use Deaduseful\Opensrs\FastLookup;
use Deaduseful\Opensrs\Lookup;
use Deaduseful\Whois\Lookup as WhoisLookup;
use Deaduseful\Whois\Client as WhoisClient;
use DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\RequestException;
use Spatie\Rdap\CouldNotFindRdapServer;
use Spatie\Rdap\Exceptions\InvalidRdapResponse;
use Spatie\Rdap\Exceptions\RdapException;
use Spatie\Rdap\Exceptions\RdapRequestTimedOut;
use Spatie\Rdap\Rdap;
use UnexpectedValueException;

use function mb_convert_encoding;

class Service
{
    private const CACHE_TTL = 5 * 60; // 5 minutes
    public const TLDS = ['com', 'co.uk', 'eu', 'co', 'biz', 'cc', 'club', 'info', 'name', 'net', 'org', 'tv', 'uk', 'org.uk', 'me.uk', 'us', 'ws'];
    public const SUGGEST_SERVICES = ['lookup', 'suggestion', 'premium', 'personal_names'];

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_TAKEN = 'taken';

    private string $query;
    private bool $test = false;
    private CacheRepository $cache;
    private ConfigRepository $config;
    private ?Rdap $rdap;
    private RdapNormaliser $rdapNormaliser;

    public function __construct(
        CacheRepository $cache,
        ConfigRepository $config,
        ?Rdap $rdap = null,
        ?RdapNormaliser $rdapNormaliser = null
    ) {
        $this->cache = $cache;
        $this->config = $config;
        $this->rdap = $rdap;
        $this->rdapNormaliser = $rdapNormaliser ?? new RdapNormaliser();
    }

    private function rdapClient(): Rdap
    {
        return $this->rdap ??= app(Rdap::class);
    }

    public function isAvailable(): ?bool
    {
        $result = $this->availability();
        if (empty($result['status'])) {
            throw new \RuntimeException($result['response'], $result['code']);
        }
        $status = $result['status'];
        if ($status === self::STATUS_AVAILABLE) {
            return true;
        } elseif ($status === self::STATUS_TAKEN) {
            return false;
        }
        return null;
    }

    public function availability(): array
    {
        $this->validateDomain($this->query);
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = $this->checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = new FastLookup();
        $result = $lookup->lookup($this->query);
        $successKeys = array_keys(FastLookup::SUCCESS_RESPONSE_CODES);
        if (in_array($result['code'], $successKeys) === false) {
            throw new UnexpectedValueException($result['response'], $result['code']);
        }
        $this->saveCacheResult($result, $key);
        return $result;
    }

    private function validateDomain(string $query): void
    {
        if (filter_var($query, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new DomainException('Invalid Domain Name');
        }
    }

    private function checkCacheResult(string $key)
    {
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }
        return null;
    }

    private function saveCacheResult($result, string $key): void
    {
        if (empty($result) === false) {
            $this->cache->put($key, $result, self::CACHE_TTL);
        }
    }

    public function whois(string $server = null): array
    {
        $this->validateDomain($this->query);
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = $this->checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $query = $this->query;
        $result = [];
        $result['tld'] = pathinfo($query, PATHINFO_EXTENSION);
        if ($server === null) {
            $serverResult = $this->getWhoisServer($result['tld']);
            $server = $this->parseWhoisServer($serverResult);
        }
        if (empty($server)) {
            throw new DomainException('Unable to find whois server for ' . $result['tld']);
        }
        $result['server'] = $server;
        if (strpos($server, ':')) {
            list($server, $port) = explode(':', $server, 2);
            $client = new WhoisClient($server, (int)$port);
            $lookup = new WhoisLookup($query, $client);
        } else {
            $client = new WhoisClient($server);
            $lookup = new WhoisLookup($query, $client);
        }
        $query = $lookup->query($query);
        $query = mb_convert_encoding($query, 'UTF-8', 'UTF-8');
        $result['result'] = $query;
        $this->saveCacheResult($result, $key);
        return $result;
    }

    private function getWhoisServer(string $query): string
    {
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = $this->checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $client = new WhoisClient('whois.iana.org');
        $lookup = new WhoisLookup($query, $client);
        $result = $lookup->query($query);
        $this->saveCacheResult($result, $key);
        return $result;
    }

    private function parseWhoisServer(string $result): string
    {
        $server = '';
        $lines = explode(PHP_EOL, $result);
        foreach ($lines as $line) {
            if (strpos($line, ':')) {
                list($key, $value) = explode(':', $line, 2);
                if ($key === 'whois') {
                    $server = trim($value);
                    break;
                }
            }
        }
        return $server;
    }

    public function rdap(?string $server = null): array
    {
        $this->validateDomain($this->query);
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = $this->checkCacheResult($key);
        if ($result) {
            return $result;
        }

        $query = $this->query;
        $dnsServer = $server;

        try {
            if ($dnsServer === null) {
                $dnsServer = $this->rdapClient()->dns()->getServerForDomain($query);
            }

            if (empty($dnsServer)) {
                throw new DomainException('Unable to find RDAP server for ' . $this->rdapNormaliser->extractTld($query));
            }

            $response = $this->rdapClient()->domain($query, dnsServer: $dnsServer);

            if ($response === null) {
                $result = $this->rdapNormaliser->availablePayload($query, rtrim($dnsServer, '/') . '/');
            } else {
                $result = $this->rdapNormaliser->normalise(
                    $query,
                    rtrim($dnsServer, '/') . '/',
                    $response
                );
            }
        } catch (CouldNotFindRdapServer $exception) {
            throw new DomainException(
                'Unable to find RDAP server for ' . $this->rdapNormaliser->extractTld($query),
                0,
                $exception
            );
        } catch (RdapRequestTimedOut | InvalidRdapResponse | RdapException | RequestException $exception) {
            throw new UnexpectedValueException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        $this->saveCacheResult($result, $key);
        return $result;
    }

    public function suggest(array $tlds = self::TLDS, array $services = self::SUGGEST_SERVICES): array
    {
        $tlds = empty($tlds) ? self::TLDS : $tlds;
        $services = empty($services) ? self::SUGGEST_SERVICES : $services;
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = $this->checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = $this->getLookup();
        $results = $lookup->suggest($this->query, $tlds, $services);
        if (empty($results['attributes'])) {
            throw new \RuntimeException($results['response'], $results['code']);
        }
        $this->saveCacheResult($results, $key);
        return $results['attributes'];
    }

    private function getLookup(): Lookup
    {
        $opensrsConfig = $this->config->get(ServiceProvider::NAMESPACE . '.opensrs');
        if (!$opensrsConfig) {
            throw new DomainException('Missing Config');
        }
        $mode = $this->test ? 'test' : 'live';
        $config = $opensrsConfig[$mode];
        if (empty($config)) {
            throw new DomainException(sprintf('Missing "%s" Config', $mode));
        }
        $configUsername = $config['username'];
        if (empty($configUsername)) {
            throw new DomainException(sprintf('Missing "%s" Config Username', $mode));
        }
        $configKey = $config['key'];
        if (empty($configKey)) {
            throw new DomainException(sprintf('Missing "%s" Config Key', $mode));
        }
        return new Lookup($configUsername, $configKey, $this->test);
    }

    public function setQuery(string $query): Service
    {
        $this->query = $query;
        return $this;
    }

    public function canTransfer(): ?bool
    {
        $result = $this->transferable();
        if (empty($result['attributes'])) {
            throw new \RuntimeException($result['response'], $result['code']);
        }
        $canTransfer = $result['attributes']['transferrable'];
        if ($canTransfer === 1) {
            return true;
        }
        if ($canTransfer === 0) {
            return false;
        }
        return null;
    }

    public function transferable(): array
    {
        $this->validateDomain($this->query);
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = $this->checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = $this->getLookup();
        $result = $lookup->lookup($this->query, 'check_transfer');
        $this->saveCacheResult($result, $key);
        return $result;
    }
}
