<?php

/**
 * Example: WHOIS lookup.
 *
 * Usage:
 *   php examples/whois.php [domain]
 *   php examples/whois.php example.com
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = examples_domain($argv);
examples_print(examples_request('GET', "/domain-api/{$domain}/whois"));
