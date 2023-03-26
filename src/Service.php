<?php

namespace Deaduseful\DomainApi;

use Deaduseful\Opensrs\FastLookup;
use Deaduseful\Opensrs\Lookup;
use Deaduseful\Whois\Lookup as WhoisLookup;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        return null;
    }

    private function saveCacheResult($result, string $key): void
    {
        if (empty($result) === false) {
            Cache::put($key, $result, self::CACHE_TTL);
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
            $lookup = new WhoisLookup($query, $server, $port);
        } else {
            $lookup = new WhoisLookup($query, $server);
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
        $lookup = new WhoisLookup($query);
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

    private function getLookup(): Lookup
    {
        $opensrsConfig = Config::get(ServiceProvider::NAMESPACE . '.opensrs');
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

    public function setQuery(string $query): Service
    {
        $this->query = $query;
        return $this;
    }

    public function canTransfer(): bool
    {
        $result = $this->transferable();
        if (empty($result['attributes'])) {
            throw new \RuntimeException($result['response'], $result['code']);
        }
        return $result['attributes']['transferrable'] === 1;
    }
}
