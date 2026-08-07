<?php

/**
 * Example: RDAP lookup.
 *
 * Usage:
 *   php examples/rdap.php [domain]
 *   php examples/rdap.php example.com
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = examples_domain($argv);
examples_print(examples_request('GET', "/domain-api/{$domain}/rdap"));
