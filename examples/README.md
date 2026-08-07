# Examples

Runnable HTTP examples for this package. They boot the `laravel/laravel` skeleton from `require-dev`, register the Domain API routes, and call them through Laravel’s HTTP kernel (no separate web server required).

## Setup

```bash
composer install
cp .env.example .env
# edit .env with OpenSRS credentials if you want transferable / suggest / available
```

| Variable | Used by |
|----------|---------|
| `DOMAIN_API_TOKEN` | API token accepted by the `api_token` middleware (examples send it automatically) |
| `OSRS_USERNAME` / `OSRS_KEY` | Live OpenSRS (`available`, and endpoints without `--test`) |
| `OSRS_TEST_USERNAME` / `OSRS_TEST_KEY` | Test / horizon OpenSRS (endpoints with `--test`; falls back to live vars if unset) |

Directory examples (`whois`, `rdap`, `lookup`) only need network access.

## Individual examples

```bash
php examples/whois.php example.com
php examples/rdap.php example.com
php examples/lookup.php example.com

php examples/available.php example.com
php examples/transferable.php example.com --test
php examples/suggest.php example --test
```

Each script prints `HTTP <status>` and pretty-printed JSON.

## Smoke runner

Runs the directory checks always, and OpenSRS checks when credentials are present. Exits `1` on failure.

```bash
composer examples
# or
php examples/smoke.php example.com
```
