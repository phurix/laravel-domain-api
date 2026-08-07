<?php

namespace Deaduseful\DomainApi\Rdap;

use Carbon\Carbon;
use Spatie\Rdap\Responses\DomainResponse;

class RdapNormaliser
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @param array<string, mixed>|null $raw
     * @return array<string, mixed>
     */
    public function normalise(
        string $domain,
        ?string $server,
        ?DomainResponse $response = null,
        ?array $raw = null
    ): array {
        $raw = $raw ?? ($response?->all() ?? []);

        if ($response === null && $raw === []) {
            return $this->availablePayload($domain, $server);
        }

        $tld = $this->extractTld($domain);

        return [
            'domain' => $domain,
            'tld' => $tld,
            'status' => $this->determineStatus($raw),
            'protocol' => 'rdap',
            'server' => $server ?? $this->extractServer($raw),
            'registrar' => $this->extractRegistrar($raw),
            'nameservers' => $this->extractNameservers($raw),
            'contacts' => $this->extractContacts($raw),
            'created_date' => $this->formatDate($response?->registrationDate() ?? $this->extractDate($raw, 'registration')),
            'updated_date' => $this->formatDate($response?->lastChangedDate() ?? $this->extractDate($raw, 'last changed')),
            'expiry_date' => $this->formatDate($response?->expirationDate() ?? $this->extractDate($raw, 'expiration')),
            'data' => [
                'handle' => $raw['handle'] ?? null,
                'ldh_name' => $raw['ldhName'] ?? null,
                'status' => $raw['status'] ?? [],
                'secure_dns' => $raw['secureDNS'] ?? null,
                'events' => $raw['events'] ?? [],
            ],
            'raw' => $raw,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function availablePayload(string $domain, ?string $server = null): array
    {
        return [
            'domain' => $domain,
            'tld' => $this->extractTld($domain),
            'status' => self::STATUS_AVAILABLE,
            'protocol' => 'rdap',
            'server' => $server,
            'registrar' => null,
            'nameservers' => [],
            'contacts' => [],
            'created_date' => null,
            'updated_date' => null,
            'expiry_date' => null,
            'data' => [
                'handle' => null,
                'ldh_name' => null,
                'status' => [],
                'secure_dns' => null,
                'events' => [],
            ],
            'raw' => [],
        ];
    }

    public function extractTld(string $domain): string
    {
        $parts = explode('.', strtolower($domain));

        return (string) end($parts);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function determineStatus(array $data): string
    {
        $statuses = $data['status'] ?? [];

        if ($statuses === []) {
            return self::STATUS_AVAILABLE;
        }

        foreach ($statuses as $status) {
            $status = strtolower((string) $status);
            if (
                str_contains($status, 'inactive')
                || str_contains($status, 'pendingdelete')
                || str_contains($status, 'redemption')
            ) {
                return self::STATUS_EXPIRED;
            }
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function extractServer(array $data): ?string
    {
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? null) === 'self' && !empty($link['href'])) {
                $parsed = parse_url($link['href']);
                if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

                    return $parsed['scheme'] . '://' . $parsed['host'] . $port . '/';
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function extractRegistrar(array $data): ?string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            if (!in_array('registrar', $entity['roles'] ?? [], true)) {
                continue;
            }

            foreach ($entity['vcardArray'][1] ?? [] as $field) {
                if (($field[0] ?? null) === 'fn') {
                    return $field[3] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public function extractNameservers(array $data): array
    {
        $nameservers = [];

        foreach ($data['nameservers'] ?? [] as $nameserver) {
            if (!empty($nameserver['ldhName'])) {
                $nameservers[] = $nameserver['ldhName'];
            }
        }

        return $nameservers;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{roles: array, data: array}>
     */
    public function extractContacts(array $data): array
    {
        $contacts = [];

        foreach ($data['entities'] ?? [] as $entity) {
            $contacts[] = [
                'roles' => $entity['roles'] ?? [],
                'data' => $entity['vcardArray'][1] ?? [],
            ];
        }

        return $contacts;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function extractDate(array $data, string $eventAction): ?Carbon
    {
        foreach ($data['events'] ?? [] as $event) {
            if (($event['eventAction'] ?? '') !== $eventAction) {
                continue;
            }

            if (empty($event['eventDate'])) {
                return null;
            }

            try {
                return Carbon::parse($event['eventDate']);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function formatDate(?Carbon $date): ?string
    {
        return $date?->toIso8601String();
    }
}
