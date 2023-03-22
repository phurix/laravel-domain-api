<?php

namespace Deaduseful\DomainApi;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    public const NAMESPACE = 'domainapi';

    /** @var string */
    public const CONFIG_PATH = __DIR__ . '/config.php';

    public function register()
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::NAMESPACE);
    }

    public function boot()
    {
        $this->getPublishes();
        $this->mapRoutes();
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
}
