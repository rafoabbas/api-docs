<?php

declare(strict_types=1);

namespace ApiDocs;

use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Collectors\YamlCollector;
use ApiDocs\Commands\ApiGenerateCommand;
use ApiDocs\Commands\GenerateCommand;
use ApiDocs\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ApiDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AttributeCollector::class);

        $this->app->singleton(YamlCollector::class, fn ($app): YamlCollector => new YamlCollector(
            config('api-docs.yaml_path'),
        ));

        $this->mergeConfigFrom(
            __DIR__ . '/../config/api-docs.php',
            'api-docs',
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'api-docs');

        $this->registerSwaggerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCommand::class,
                ApiGenerateCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/api-docs.php' => config_path('api-docs.php'),
            ], 'api-docs-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/api-docs'),
            ], 'api-docs-views');
        }
    }

    private function registerSwaggerRoutes(): void
    {
        if (! config('api-docs.swagger.enabled', true)) {
            return;
        }

        $path = config('api-docs.swagger.path', '/api/docs');
        $middleware = config('api-docs.swagger.middleware', []);

        Route::middleware($middleware)->group(function () use ($path): void {
            Route::get($path, [SwaggerController::class, 'index'])->name('api-docs.swagger');
            Route::get($path . '/openapi.json', [SwaggerController::class, 'openapi'])->name('api-docs.openapi');
            Route::options($path . '/openapi.json', fn () => response('', 200)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept',
            ]));
        });
    }
}
