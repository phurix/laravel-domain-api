<?php

/**
 * Example: domain availability (OpenSRS fast lookup).
 *
 * Usage:
 *   php examples/available.php [domain]
 *   php examples/available.php example.com
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = examples_domain($argv);

if (! examples_has_opensrs()) {
    fwrite(STDERR, "Set OSRS_USERNAME and OSRS_KEY in .env (see .env.example).\n");
    exit(1);
}

examples_print(examples_request('GET', "/domain-api/{$domain}/available"));
