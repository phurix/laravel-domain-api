<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\TestCase;

class RouteGenerationTest extends TestCase
{
    public function test_base_and_server_routes_have_unique_names_and_generate_without_supplier_calls(): void
    {
        $this->withRouter(function (Router $router, UrlGenerator $generator): void {
            foreach (['whois', 'rdap'] as $method) {
                $base = $generator->route("domain-api.{$method}", ['query' => 'example.invalid']);
                $server = $generator->route("domain-api.{$method}.server", ['query' => 'example.invalid', 'server' => 'server.invalid']);

                $this->assertSame("http://fixture.invalid/domain-api/example.invalid/{$method}", $base);
                $this->assertSame("http://fixture.invalid/domain-api/example.invalid/{$method}/server.invalid", $server);
                $this->assertSame("domain-api.{$method}", $router->getRoutes()->match(Request::create($base))->getName());
                $this->assertSame("domain-api.{$method}.server", $router->getRoutes()->match(Request::create($server))->getName());
            }

            $compiled = $router->getRoutes()->compile();
            $this->assertCount(8, $compiled['attributes']);
        });
    }

    public function test_rdap_server_url_with_path_matches_the_named_endpoint(): void
    {
        $this->withRouter(function (Router $router, UrlGenerator $generator): void {
            $server = 'https://rdap.example.invalid/registry/v1';
            $url = $generator->route('domain-api.rdap.server', ['query' => 'example.invalid', 'server' => $server]);
            $matched = $router->getRoutes()->match(Request::create($url));

            $this->assertSame('domain-api.rdap.server', $matched->getName());
            $this->assertSame($server, $matched->parameter('server'));
            $this->assertSame('Deaduseful\\DomainApi\\Controller@rdap', $matched->getActionName());

            $encoded = $generator->route('domain-api.rdap', ['query' => 'example.invalid']) . '/' . rawurlencode($server);
            $this->assertSame($server, $router->getRoutes()->match(Request::create($encoded))->parameter('server'));
        });
    }

    private function withRouter(callable $assertions): void
    {
        $previousApplication = Route::getFacadeApplication();
        $container = new Container();
        $router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $router);
        Route::setFacadeApplication($container);
        Route::clearResolvedInstance('router');

        try {
            Route::prefix('domain-api')->name('domain-api.')->namespace('Deaduseful\\DomainApi')
                ->group(dirname(__DIR__, 2) . '/src/routes.php');
            $router->getRoutes()->refreshNameLookups();
            $generator = new UrlGenerator($router->getRoutes(), Request::create('http://fixture.invalid'));
            $assertions($router, $generator);
        } finally {
            Route::clearResolvedInstance('router');
            Route::setFacadeApplication($previousApplication);
        }
    }
}
