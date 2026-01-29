<?php

declare(strict_types=1);

namespace ApiDocs\Tests;

use ApiDocs\ApiDocsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ApiDocsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('api-docs.yaml_path', __DIR__.'/fixtures/yaml');
        $app['config']->set('api-docs.exclude_prefixes', ['_', 'sanctum', 'telescope']);
    }
}
