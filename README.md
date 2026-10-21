# Laravel Domain API

Laravel package for domain **availability**, **transferability**, **suggestions**, **WHOIS**, **RDAP**, and a unified **lookup** (RDAP first, WHOIS fallback).

It exposes HTTP routes, Artisan commands, and a `Service` class you can inject into your own code.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- OpenSRS credentials for availability, transferability, and suggestions
- Network access for WHOIS / RDAP / lookup (directory lookups)

Private Composer dependencies (`deaduseful/whois-lookup`, `deaduseful/opensrs-lookup`) are pulled from GitHub VCS repositories. Configure GitHub OAuth for Composer if those repos are private.

## Installation

Add these VCS entries to the consuming Laravel application's root `composer.json` (merge them with any existing repositories):

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/phurix/laravel-domain-api.git" },
        { "type": "vcs", "url": "https://github.com/deaduseful/whois-lookup.git" },
        { "type": "vcs", "url": "https://github.com/deaduseful/opensrs-lookup.git" }
    ]
}
```

[Composer loads repositories from the root project only](https://getcomposer.org/doc/05-repositories.md#repository). The entries in this package's own manifest support working on the SDK; they are not inherited by consuming applications. Authentication alone does not make a private repository discoverable. The SDK's Composer name remains `deaduseful/laravel-domain-api`, even though its repository is under `phurix`.

Arrange read access to all three private repositories using your existing credential manager or a CI secret configured outside tracked files. Use repository-scoped, read-only credentials where supported; the SDK and its two private dependencies belong to different GitHub owners, so fine-grained credentials may need separate owner scopes. Do not commit tokens or auth files. Keep the application's repository entries for the private dependencies after requiring this package.

Then install the approved published release:

```bash
composer require deaduseful/laravel-domain-api
```

Composer selects a published stable version by default. This command does not promise an unreleased development-branch fix; use the release constraint approved for your application. Retain and review the resulting application lockfile.

Register the providers (this package does not use Laravel package auto-discovery):

```php
// config/app.php (Laravel 10) or bootstrap/providers.php (Laravel 11+)
Spatie\Rdap\RdapServiceProvider::class,
Deaduseful\DomainApi\ServiceProvider::class,
```

Publish the config (optional):

```bash
php artisan vendor:publish --provider="Deaduseful\DomainApi\ServiceProvider" --tag=config
```

## Configuration

Environment variables (see `.env.example`):

| Variable | Purpose |
|----------|---------|
| `DOMAIN_API_TOKEN` | Token accepted by the `api_token` middleware |
| `DOMAIN_API_PREFIX` | Route prefix (default `domain-api`) |
| `DOMAIN_API_MIDDLEWARE` | Route middleware (default `api_token`) |
| `DOMAIN_API_NAME` | Route name prefix (default `domain-api.`) |
| `OSRS_USERNAME` / `OSRS_KEY` | Live OpenSRS credentials |
| `OSRS_TEST_USERNAME` / `OSRS_TEST_KEY` | Test / Horizon OpenSRS (falls back to live vars if unset) |
| `DOMAIN_API_RDAP_TIMEOUT` | RDAP HTTP timeout in seconds (default `30`) |
| `DOMAIN_API_RDAP_USER_AGENT` | RDAP user agent string |

Published config lives at `config/domainapi.php`.

Results from directory and OpenSRS calls are cached for **5 minutes**.

## Authentication

By default, HTTP routes use the `api_token` middleware. Pass the token as either:

- `Authorization: Bearer <token>`, or
- `api_token` query / request input

```bash
curl -H "Authorization: Bearer $DOMAIN_API_TOKEN" \
  "https://example.test/domain-api/example.com/lookup"
```

Set `DOMAIN_API_MIDDLEWARE` to another middleware (or an empty list via config) if you want different auth.

## HTTP API

All routes are under `/{prefix}/{query}/…` (default prefix `domain-api`).

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/{query}/available` | Registration availability via OpenSRS fast lookup |
| `GET` | `/{query}/transferable?test=1` | Transfer check via OpenSRS (`test` uses test credentials) |
| `GET` | `/{query}/suggest?test=1&tlds=com,net&services=lookup,suggestion` | Domain suggestions via OpenSRS |
| `GET` | `/{query}/whois` | Raw WHOIS (`/{query}/whois/{server}` to force a server) |
| `GET` | `/{query}/rdap` | Normalised RDAP (`/{query}/rdap/{server}` to force a server) |
| `GET` | `/{query}/lookup` | RDAP-first lookup with WHOIS fallback |

`available`, `transferable`, and `suggest` need OpenSRS credentials. `whois`, `rdap`, and `lookup` only need network access.

### Lookup response

`GET …/lookup` returns a shared LookupResult shape. RDAP success sets `"fallback": false`. On hard RDAP failure (no server, timeout, invalid response), WHOIS is used and `"fallback": true`.

```json
{
  "domain": "example.com",
  "tld": "com",
  "status": "active",
  "protocol": "rdap",
  "server": "https://rdap.verisign.com/com/v1/",
  "registrar": "…",
  "nameservers": [],
  "contacts": [],
  "created_date": "…",
  "updated_date": "…",
  "expiry_date": "…",
  "data": {},
  "raw": {},
  "fallback": false
}
```

WHOIS fallback keeps the same top-level keys; structured fields stay empty/`unknown`, with text in `data.whois_text` and `raw.whois`.

**Important:** `status: available` on lookup/RDAP means “not in the directory / unknown to RDDS”, not “safe to register”. Use `…/available` (OpenSRS) for buy-path availability.

Hard failures on `whois`, `rdap`, and `lookup` return HTTP `400` with `{ "error": "…" }`.

## Artisan commands

```bash
php artisan domainapi:check example.com
php artisan domainapi:whois example.com
php artisan domainapi:rdap example.com
php artisan domainapi:rdap example.com --server=https://rdap.example/
php artisan domainapi:lookup example.com
php artisan domainapi:suggest example
```

## Programmatic usage

```php
use Deaduseful\DomainApi\Service;

public function __construct(private Service $domains) {}

public function show(string $domain): array
{
    return $this->domains
        ->setQuery($domain)
        ->lookup();
}
```

Useful methods on `Service`:

- `availability()` / `isAvailable()`
- `transferable()` / `canTransfer()` (call `setTest(true)` for Horizon)
- `suggest($tlds, $services)`
- `whois($server = null)`
- `rdap($server = null)`
- `lookup()`

## Examples

Runnable HTTP examples boot the `laravel/laravel` skeleton from `require-dev` and hit the package routes through the HTTP kernel (no separate web server).

```bash
composer install
cp .env.example .env
# set DOMAIN_API_TOKEN and OpenSRS credentials as needed

composer examples
# or
php examples/whois.php example.com
php examples/lookup.php example.com
php examples/available.php example.com
php examples/transferable.php example.com --test
php examples/suggest.php example --test
```

See [examples/README.md](examples/README.md) for details.

## Testing

```bash
composer test    # PHPUnit
composer phpcs   # coding standards
composer qa      # both
```

## Licence

MIT


## Release candidate: 2.2.1

PR #5 preserves the named base endpoints `domain-api.whois` and `domain-api.rdap`. Server-specific endpoints use distinct `.server` names. The RDAP server is a trailing path parameter that accepts a full base URL, including its path; HTTP clients should percent-encode the server URL as one path value. Base and server routes, incoming matching and route-cache compilation are covered without supplier calls. The standalone route tests restore the previous facade state even when an assertion fails.

After review and a successful supported-PHP CI matrix, merge the route correction into `main` and tag that reviewed merge commit `v2.2.1`. Do not add a manual Composer version or move an existing tag. This repository keeps the existing package identity `deaduseful/laravel-domain-api`; no default-branch identity change is required.

The portal can then use `deaduseful/laravel-domain-api: ^2.2.1` through its existing private VCS repository. Resolve the release afresh, verify the lockfile's source revision belongs to the reviewed release, perform a clean installation/audit and run the portal's isolated tests and PHPStan gate. The private WHOIS/OpenSRS transitive packages still need the consumer's configured access and root-level repositories; this release does not change that boundary. No credential should be copied into the repository or release assets.

Tags and publication are a separate approval step; preparing this candidate does not merge or publish it.

## CI credential handling

The PHP 8.2/8.3/8.4 QA job uses `COMPOSER_AUTH_TOKEN` only in the dependency-install step through step-scoped `COMPOSER_AUTH`. Configure that secret with read access to `deaduseful/whois-lookup` and `deaduseful/opensrs-lookup`; the workflow does not narrow an existing token's provider permissions. Checkout uses the read-only GitHub job token without persisting it in Git configuration.

Installation disables dependency scripts and plugins while the Composer credential is available. An always-run cleanup removes the isolated Composer home before PHPUnit and PHPCS run. Only downloaded archives are cached; Composer home/auth files are not cached. Fork pull requests skip this private-dependency job instead of receiving credentials. Ordinary same-repository PR workflow changes still require trusted review; step scoping does not make an arbitrary workflow from an untrusted author safe.
