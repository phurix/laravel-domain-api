<?php

/**
 * Example: transferability check (OpenSRS).
 *
 * Usage:
 *   php examples/transferable.php [domain] [--test]
 *   php examples/transferable.php example.com --test
 *
 * Pass --test to use domainapi.opensrs.test credentials / OpenSRS horizon.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = examples_domain($argv);
$test = examples_wants_test($argv);

if (! examples_has_opensrs($test)) {
    $hint = $test
        ? 'Set OSRS_TEST_USERNAME / OSRS_TEST_KEY (or OSRS_USERNAME / OSRS_KEY) in .env.'
        : 'Set OSRS_USERNAME and OSRS_KEY in .env (see .env.example).';
    fwrite(STDERR, $hint . PHP_EOL);
    exit(1);
}

$query = $test ? ['test' => '1'] : [];
examples_print(examples_request('GET', "/domain-api/{$domain}/transferable", $query));
