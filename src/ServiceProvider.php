<?php

namespace Deaduseful\DomainApi;

use Deaduseful\DomainApi\Commands\CheckCommand;
use Deaduseful\DomainApi\Commands\SuggestCommand;
use Deaduseful\DomainApi\Commands\WhoisCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    public const NAMESPACE = 'domainapi';

    /** @var string */
    public const CONFIG_PATH = __DIR__ . '/config.php';
    private const COMMANDS = [
        CheckCommand::class,
        WhoisCommand::class,
        SuggestCommand::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::NAMESPACE);
    }

    public function boot()
    {
        $this->mapRoutes();
        if ($this->app->runningInConsole()) {
            $this->getPublishes();
            $this->getCommands();
        } else {
            $this->addMiddleware();
        }
    }

    private function getPublishes(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path(self::NAMESPACE . '.php'),
        ], 'config');
    }

    protected function mapRoutes()
    {
        $config = Config::get(self::NAMESPACE . '.routes');
        $prefix = $config['prefix'];
        $name = $config['name'];
        $middleware = $config['middleware'];
        Route::prefix($prefix)
            ->name($name)
            ->middleware($middleware)
            ->namespace(__NAMESPACE__)
            ->group(__DIR__ . DIRECTORY_SEPARATOR . 'routes.php');
    }

    /**
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private function addMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('api_token', ApiToken::class);
    }

    private function getCommands(): void
    {
        $this->commands(self::COMMANDS);
    }
}
