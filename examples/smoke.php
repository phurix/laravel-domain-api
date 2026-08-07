<?php

/**
 * Smoke runner: exercises each HTTP example and exits non-zero on failure.
 *
 * Usage:
 *   php examples/smoke.php [domain]
 *   composer examples
 *
 * OpenSRS endpoints are skipped (with a note) when credentials are missing.
 * Directory endpoints (lookup / rdap / whois) always run and need network access.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = examples_domain($argv);
$suggestQuery = pathinfo($domain, PATHINFO_FILENAME) ?: 'example';
$failures = 0;

/**
 * @param  callable(array{status: int, json: ?array, raw: string}): bool  $ok
 */
$check = static function (string $label, array $result, callable $ok) use (&$failures): void {
    $pass = $ok($result);
    if (! $pass) {
        $failures++;
    }

    $preview = $result['json'] ?? $result['raw'];
    if (is_array($preview)) {
        $preview = json_encode($preview, JSON_UNESCAPED_SLASHES);
    }
    $preview = substr((string) $preview, 0, 200);

    echo ($pass ? 'PASS' : 'FAIL') . "  [{$result['status']}] {$label}" . PHP_EOL;
    echo "      {$preview}" . PHP_EOL . PHP_EOL;
};

echo "=== Domain API examples smoke ({$domain}) ===" . PHP_EOL . PHP_EOL;

echo '-- Directory (no OpenSRS credentials required) --' . PHP_EOL;

$check(
    "GET /domain-api/{$domain}/lookup",
    examples_request('GET', "/domain-api/{$domain}/lookup"),
    static fn (array $r): bool => $r['status'] === 200
        && is_array($r['json'])
        && isset($r['json']['protocol'], $r['json']['domain'], $r['json']['fallback'])
        && in_array($r['json']['protocol'], ['rdap', 'whois'], true)
);

$check(
    "GET /domain-api/{$domain}/rdap",
    examples_request('GET', "/domain-api/{$domain}/rdap"),
    static fn (array $r): bool => $r['status'] === 200
        && is_array($r['json'])
        && ($r['json']['protocol'] ?? null) === 'rdap'
        && array_key_exists('raw', $r['json'])
);

$check(
    "GET /domain-api/{$domain}/whois",
    examples_request('GET', "/domain-api/{$domain}/whois"),
    static fn (array $r): bool => $r['status'] === 200
        && is_array($r['json'])
        && isset($r['json']['server'], $r['json']['result'], $r['json']['tld'])
);

echo '-- OpenSRS (requires credentials in .env) --' . PHP_EOL;

if (examples_has_opensrs(true)) {
    $check(
        "GET /domain-api/{$domain}/transferable?test=1",
        examples_request('GET', "/domain-api/{$domain}/transferable", ['test' => '1']),
        static fn (array $r): bool => $r['status'] === 200
            && is_array($r['json'])
            && (
                isset($r['json']['attributes'])
                || isset($r['json']['response'])
                || isset($r['json']['code'])
            )
    );

    $check(
        "GET /domain-api/{$suggestQuery}/suggest?test=1",
        examples_request('GET', "/domain-api/{$suggestQuery}/suggest", [
            'test' => '1',
            'tlds' => 'com',
            'services' => 'lookup',
        ]),
        static fn (array $r): bool => $r['status'] === 200
            && is_array($r['json'])
            && $r['json'] !== []
    );

    if (examples_has_opensrs(false)) {
        $check(
            "GET /domain-api/{$domain}/available",
            examples_request('GET', "/domain-api/{$domain}/available"),
            static fn (array $r): bool => $r['status'] === 200
                && is_array($r['json'])
                && isset($r['json']['status'], $r['json']['code'])
        );
    } else {
        echo "SKIP  available (set OSRS_USERNAME / OSRS_KEY for live fast-lookup)" . PHP_EOL . PHP_EOL;
    }
} else {
    echo "SKIP  transferable / suggest / available"
        . " (set OSRS_TEST_* or OSRS_* in .env — see .env.example)" . PHP_EOL . PHP_EOL;
}

if ($failures > 0) {
    echo "Failed: {$failures}" . PHP_EOL;
    exit(1);
}

echo 'All checks passed.' . PHP_EOL;
exit(0);
