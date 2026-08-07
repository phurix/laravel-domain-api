<?php

/**
 * Shared bootstrap for package examples.
 *
 * Boots the laravel/laravel skeleton (require-dev) with this package registered,
 * loads credentials from the package-root .env, and exposes helpers for HTTP calls.
 */

declare(strict_types=1);

use Deaduseful\DomainApi\ApiToken;
use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Spatie\Rdap\RdapServiceProvider;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (! is_dir($root . '/vendor/laravel/laravel')) {
    fwrite(STDERR, "laravel/laravel is missing. Run `composer install` from the package root first.\n");
    exit(1);
}

/**
 * Load KEY=VALUE pairs from .env into the process environment.
 */
function examples_load_env(string $path): void
{
    if (! is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

examples_load_env($root . '/.env');

/**
 * @return non-empty-string
 */
function examples_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $default;
    }

    return (string) $value;
}

$app = require $root . '/vendor/laravel/laravel/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$app->register(RdapServiceProvider::class);
$app->register(ServiceProvider::class);

// CLI SAPI skips middleware aliasing in ServiceProvider; register it for HTTP examples.
$app->make('router')->aliasMiddleware('api_token', ApiToken::class);

$token = examples_env('DOMAIN_API_TOKEN', 'example-api-token');
$app['config']->set('domainapi.api.token', $token);
$app['config']->set('cache.default', 'array');

$liveUser = examples_env('OSRS_USERNAME');
$liveKey = examples_env('OSRS_KEY');
$testUser = examples_env('OSRS_TEST_USERNAME', $liveUser);
$testKey = examples_env('OSRS_TEST_KEY', $liveKey);

$app['config']->set('domainapi.opensrs.live', [
    'username' => $liveUser,
    'key' => $liveKey,
]);
$app['config']->set('domainapi.opensrs.test', [
    'username' => $testUser,
    'key' => $testKey,
]);

$httpKernel = $app->make(HttpKernel::class);

/**
 * Perform an HTTP request against the package routes.
 *
 * @param  array<string, scalar|null>  $query
 * @return array{status: int, json: ?array, raw: string}
 */
function examples_request(string $method, string $uri, array $query = []): array
{
    global $httpKernel, $token;

    $query = array_merge(['api_token' => $token], $query);
    $request = Request::create($uri, $method, $query);
    $response = $httpKernel->handle($request);
    $body = $response->getContent() ?: '';
    $json = json_decode($body, true);
    $httpKernel->terminate($request, $response);

    return [
        'status' => $response->getStatusCode(),
        'json' => is_array($json) ? $json : null,
        'raw' => $body,
    ];
}

/**
 * Pretty-print a response to STDOUT.
 *
 * @param  array{status: int, json: ?array, raw: string}  $result
 */
function examples_print(array $result): void
{
    echo 'HTTP ' . $result['status'] . PHP_EOL;
    if (is_array($result['json'])) {
        echo json_encode($result['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    echo $result['raw'] . PHP_EOL;
}

/**
 * First non-flag CLI argument, or a default domain.
 *
 * @param  list<string>  $argv
 */
function examples_domain(array $argv, string $default = 'example.com'): string
{
    foreach (array_slice($argv, 1) as $arg) {
        if (! str_starts_with($arg, '-')) {
            return $arg;
        }
    }

    return $default;
}

/**
 * Whether --test was passed on the CLI.
 *
 * @param  list<string>  $argv
 */
function examples_wants_test(array $argv): bool
{
    return in_array('--test', $argv, true);
}

/**
 * True when OpenSRS credentials are available for the requested mode.
 */
function examples_has_opensrs(bool $test = false): bool
{
    if ($test) {
        $user = examples_env('OSRS_TEST_USERNAME', examples_env('OSRS_USERNAME'));
        $key = examples_env('OSRS_TEST_KEY', examples_env('OSRS_KEY'));
    } else {
        $user = examples_env('OSRS_USERNAME');
        $key = examples_env('OSRS_KEY');
    }

    return $user !== '' && $key !== '';
}
