<?php

namespace Deaduseful\DomainApi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    public const CONFIG_KEY = 'domainapi';

    /** @var string */
    public const CONFIG_PATH = __DIR__ . '/config.php';

    public function register()
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);
    }

    public function boot()
    {
        $this->getPublishes();
        $this->handleRoutes();
    }

    private function getPublishes(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path(self::CONFIG_KEY . '.php'),
        ], 'config');
    }

    protected function handleRoutes()
    {
        Route::macro(self::CONFIG_KEY . 'Routes', function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        });
    }
}
