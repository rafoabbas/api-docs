<?php

declare(strict_types=1);

namespace ApiDocs;

use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Collectors\YamlCollector;
use ApiDocs\Commands\ApiGenerateCommand;
use ApiDocs\Commands\GenerateCommand;
use Illuminate\Support\ServiceProvider;

class ApiDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AttributeCollector::class);

        $this->app->singleton(YamlCollector::class, function ($app): YamlCollector {
            return new YamlCollector(
                config('api-docs.yaml_path')
            );
        });

        $this->mergeConfigFrom(
            __DIR__.'/../config/api-docs.php',
            'api-docs'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCommand::class,
                ApiGenerateCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/api-docs.php' => config_path('api-docs.php'),
            ], 'api-docs-config');
        }
    }
}
