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
        $container = new Container();
        $router = new Router(new Dispatcher($container), $container);
        $container->instance('router', $router);
        Route::setFacadeApplication($container);
        Route::clearResolvedInstances();

        Route::prefix('domain-api')->name('domain-api.')->group(dirname(__DIR__, 2) . '/../src/routes.php');
        $router->getRoutes()->refreshNameLookups();
        $generator = new UrlGenerator($router->getRoutes(), Request::create('http://fixture.invalid'));

        foreach (['whois', 'rdap'] as $method) {
            $this->assertSame(
                "http://fixture.invalid/domain-api/example.invalid/{$method}",
                $generator->route("domain-api.{$method}", ['query' => 'example.invalid']),
            );
            $this->assertSame(
                "http://fixture.invalid/domain-api/example.invalid/{$method}/server.invalid",
                $generator->route("domain-api.{$method}.server", ['query' => 'example.invalid', 'server' => 'server.invalid']),
            );
        }

        Route::clearResolvedInstances();
        Route::setFacadeApplication(null);
    }
}
