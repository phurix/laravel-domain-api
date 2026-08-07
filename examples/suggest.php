<?php

/**
 * Example: domain name suggestions (OpenSRS).
 *
 * Usage:
 *   php examples/suggest.php [query] [--test]
 *   php examples/suggest.php example --test
 *
 * Pass --test to use domainapi.opensrs.test credentials / OpenSRS horizon.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$queryString = examples_domain($argv, 'example');
$test = examples_wants_test($argv);

if (! examples_has_opensrs($test)) {
    $hint = $test
        ? 'Set OSRS_TEST_USERNAME / OSRS_TEST_KEY (or OSRS_USERNAME / OSRS_KEY) in .env.'
        : 'Set OSRS_USERNAME and OSRS_KEY in .env (see .env.example).';
    fwrite(STDERR, $hint . PHP_EOL);
    exit(1);
}

$query = [
    'tlds' => 'com,net',
    'services' => 'lookup',
];
if ($test) {
    $query['test'] = '1';
}

examples_print(examples_request('GET', "/domain-api/{$queryString}/suggest", $query));
