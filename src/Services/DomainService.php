<?php

namespace Deaduseful\Services;

use Deaduseful\Opensrs\FastLookup;
use Deaduseful\Opensrs\Lookup;
use Deaduseful\Whois\Lookup as WhoisLookup;
use DomainException;
use Illuminate\Support\Facades\Cache;
use UnexpectedValueException;

class DomainService
{
    const CACHE_TTL = 5 * 60; // 5 minutes

    /** @const array Tlds */
    const TLDS = ['com', 'co.uk', 'eu', 'co', 'biz', 'cc', 'club', 'info', 'name', 'net', 'org', 'tv', 'uk', 'org.uk', 'me.uk', 'us', 'ws'];
    const SUGGEST_SERVICES = ['lookup', 'suggestion', 'premium', 'personal_names'];

    public static function availability(string $query): array
    {
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = self::checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = new FastLookup();
        $result = $lookup->lookup($query);
        $successKeys = array_keys(FastLookup::SUCCESS_RESPONSE_CODES);
        if (in_array($result['code'], $successKeys) === false) {
            throw new UnexpectedValueException($result['response'], $result['code']);
        }
        self::saveCacheResult($result, $key);
        return $result;
    }

    private static function checkCacheResult(string $key)
    {
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        return null;
    }

    private static function saveCacheResult($result, string $key): void
    {
        if (empty($result) === false) {
            Cache::put($key, $result, self::CACHE_TTL);
        }
    }

    public static function whois(string $query, string $server = null): array
    {
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = self::checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $result = [];
        $result['tld'] = pathinfo($query, PATHINFO_EXTENSION);
        if ($server === null) {
            $serverResult = self::getWhoisServer($result['tld']);
            $server = self::parseWhoisServer($serverResult);
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
        self::saveCacheResult($result, $key);
        return $result;
    }

    private static function getWhoisServer(string $query): string
    {
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = self::checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = new WhoisLookup($query);
        $result = $lookup->query($query);
        self::saveCacheResult($result, $key);
        return $result;
    }

    private static function parseWhoisServer(string $result): string
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

    public static function transferable(string $query, bool $test = false): array
    {
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = self::checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = self::getLookup($test);
        $result = $lookup->lookup($query, 'check_transfer');
        self::saveCacheResult($result, $key);
        return $result;
    }

    public static function suggest(string $query, array $tlds = self::TLDS, array $services = self::SUGGEST_SERVICES, bool $test = false): array
    {
        $tlds = empty($tlds) ? self::TLDS : $tlds;
        $services = empty($services) ? self::SUGGEST_SERVICES : $services;
        $key = __CLASS__ . PATH_SEPARATOR . __FUNCTION__ . PATH_SEPARATOR . serialize(func_get_args());
        $result = self::checkCacheResult($key);
        if ($result) {
            return $result;
        }
        $lookup = self::getLookup($test);
        $results = $lookup->suggest($query, $tlds, $services);
        $attributes = $results['attributes'];
        self::saveCacheResult($attributes, $key);
        return $attributes;

    }

    private static function getLookup(bool $test): Lookup
    {
        $opensrsConfig = config('opensrs');
        if (!$opensrsConfig) {
            throw new DomainException('Missing Config');
        }
        $mode = $test ? 'test' : 'live';
        $config = $opensrsConfig[$mode];
        if (!$config) {
            throw new DomainException('Missing Config');
        }
        $configUsername = $config['username'];
        $configKey = $config['key'];
        return new Lookup($configUsername, $configKey, $test);
    }
}
