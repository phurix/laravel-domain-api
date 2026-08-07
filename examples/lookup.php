<?php

/**
 * Example: unified lookup (RDAP with WHOIS fallback).
 *
 * Usage:
 *   php examples/lookup.php [domain]
 *   php examples/lookup.php example.com
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = examples_domain($argv);
examples_print(examples_request('GET', "/domain-api/{$domain}/lookup"));
