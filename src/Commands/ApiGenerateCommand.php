<?php

declare(strict_types=1);

namespace ApiDocs\Commands;

use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Collectors\RequestMerger;
use ApiDocs\Collectors\YamlCollector;
use ApiDocs\Generators\CollectionGenerator;
use ApiDocs\Generators\EnvironmentGenerator;
use ApiDocs\Generators\OpenApiGenerator;
use Illuminate\Console\Command;

class ApiGenerateCommand extends Command
{
    protected $signature = 'api:generate
                            {--format=both : Output format: postman, openapi, or both}
                            {--yaml= : Custom YAML files path (overrides config)}
                            {--output= : Output directory for generated files}
                            {--name= : Collection/API name (default: app name)}
                            {--exclude=* : Prefixes to exclude (e.g., --exclude=admin)}
                            {--openapi-format= : OpenAPI output format: yaml or json (default: from config)}';

    protected $description = 'Generate API documentation (Postman collection and/or OpenAPI spec) from PHP attributes and YAML files';

    public function __construct(
        private readonly AttributeCollector $attributeCollector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');

        if (! in_array($format, ['postman', 'openapi', 'both'])) {
            $this->error("Invalid format: {$format}. Use 'postman', 'openapi', or 'both'.");

            return self::FAILURE;
        }

        $this->info('Collecting API definitions...');

        // Configure exclude prefixes
        $excludePrefixes = $this->option('exclude');
        $defaultPrefixes = config('api-docs.exclude_prefixes', ['_', 'sanctum', 'telescope', 'storage', 'mcp']);

        if (is_array($excludePrefixes) && count($excludePrefixes) > 0) {
            $this->attributeCollector->setExcludePrefixes(array_merge($defaultPrefixes, $excludePrefixes));
        } else {
            $this->attributeCollector->setExcludePrefixes($defaultPrefixes);
        }

        // Collect from attributes
        $attributeRequests = $this->attributeCollector->collect();
        $this->line("  Found {$this->countRequests($attributeRequests)} routes from PHP attributes.");

        // Collect from YAML
        $yamlPath = $this->option('yaml') ?? config('api-docs.yaml_path');
        $yamlCollector = new YamlCollector($yamlPath);
        $yamlRequests = $yamlCollector->collect();
        $this->line("  Found {$this->countRequests($yamlRequests)} requests from YAML files.");

        // Merge requests (attributes take priority)
        $merger = new RequestMerger;
        $requests = $merger->merge($attributeRequests, $yamlRequests);

        if (count($requests) === 0) {
            $this->error('No API definitions found.');

            return self::FAILURE;
        }

        $this->info("Total: {$this->countRequests($requests)} unique endpoints.");
        $this->newLine();

        $success = true;
        $outputDir = $this->option('output');

        if ($format === 'postman' || $format === 'both') {
            if (! $this->generatePostman($requests, $outputDir)) {
                $success = false;
            }
            $this->generateEnvironments($outputDir);
        }

        if (($format === 'openapi' || $format === 'both') && ! $this->generateOpenApi($requests, $outputDir)) {
            $success = false;
        }

        if ($success) {
            $this->newLine();
            $this->showFolderSummary($requests);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, \ApiDocs\Data\RequestData>  $requests
     */
    private function generatePostman(array $requests, ?string $outputDir): bool
    {
        $this->info('Generating Postman collection...');

        $collectionName = $this->option('name') ?? config('app.name', 'API');
        $generator = new CollectionGenerator($collectionName);

        $this->configureApiVariables($generator);

        $collection = $generator->generate($requests);

        $outputPath = $this->getPostmanOutputPath($outputDir);
        $this->ensureDirectoryExists(dirname($outputPath));

        file_put_contents(
            $outputPath,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        $this->line("  Postman collection: {$outputPath}");

        return true;
    }

    /**
     * @param  array<int, \ApiDocs\Data\RequestData>  $requests
     */
    private function generateOpenApi(array $requests, ?string $outputDir): bool
    {
        $this->info('Generating OpenAPI specification...');

        $generator = new OpenApiGenerator;
        $this->configureOpenApiGenerator($generator);

        $openapiFormat = $this->option('openapi-format') ?? config('api-docs.openapi.output_format', 'yaml');
        $outputPath = $this->getOpenApiOutputPath($outputDir, $openapiFormat);
        $this->ensureDirectoryExists(dirname($outputPath));

        $output = $openapiFormat === 'json' ? $generator->generateJson($requests) : $generator->generateYaml($requests);

        file_put_contents($outputPath, $output);

        $this->line("  OpenAPI specification: {$outputPath}");

        return true;
    }

    private function configureApiVariables(CollectionGenerator $generator): void
    {
        $apiDomain = config('app.api_domain');

        if (! $apiDomain) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $apiDomain = "api.{$host}";
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        $generator->setVariables([
            'API_URL' => "{$scheme}://{$apiDomain}",
            'API_URL_V1' => "{$scheme}://{$apiDomain}/v1",
            'BEARER_TOKEN' => '',
            'PHONE' => '905050414874',
            'OTP_CODE' => '',
            'ORDER_UUID' => '',
        ]);

        // Add custom variables from config
        $customVariables = config('api-docs.variables', []);

        foreach ($customVariables as $key => $value) {
            $generator->addVariable($key, $value);
        }
    }

    private function configureOpenApiGenerator(OpenApiGenerator $generator): void
    {
        $config = config('api-docs.openapi', []);

        $generator->setTitle($config['title'] ?? config('app.name', 'API Documentation'));
        $generator->setVersion($config['version'] ?? '1.0.0');

        if (! empty($config['description'])) {
            $generator->setDescription($config['description']);
        }

        $servers = $config['servers'] ?? [];

        if (count($servers) > 0) {
            $generator->setServers($servers);
        } else {
            // Build default server URL
            $apiDomain = config('app.api_domain');

            if (! $apiDomain) {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
                $apiDomain = "api.{$host}";
            }
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
            $generator->addServer("{$scheme}://{$apiDomain}", 'API Server');
        }
    }

    private function generateEnvironments(?string $outputDir): void
    {
        $environments = config('api-docs.environments', []);

        if (count($environments) === 0) {
            return;
        }

        $this->info('Generating Postman environments...');

        $generator = new EnvironmentGenerator;
        $timestamp = time();
        $appName = config('app.name', 'API');

        foreach ($environments as $key => $variables) {
            // Build environment name: "{APP_NAME} ({key})" or just "{APP_NAME}" for local
            $environmentName = $key === 'local'
                ? $appName
                : "{$appName} ({$key})";

            $environment = $generator->generate($environmentName, $variables);

            $outputPath = $this->getEnvironmentOutputPath($outputDir, $key, $timestamp);
            $this->ensureDirectoryExists(dirname($outputPath));

            file_put_contents(
                $outputPath,
                json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            $this->line("  Environment ({$key}): {$outputPath}");
        }
    }

    private function getEnvironmentOutputPath(?string $outputDir, string $name, int $timestamp): string
    {
        $filename = "{$timestamp}-{$name}.postman_environment.json";

        if ($outputDir !== null) {
            return rtrim($outputDir, '/') . "/postman/{$filename}";
        }

        $configPath = config('api-docs.output.postman_path', storage_path('app/collections'));

        return "{$configPath}/{$filename}";
    }

    private function getPostmanOutputPath(?string $outputDir): string
    {
        $timestamp = time();
        $filename = "{$timestamp}-collection.json";

        if ($outputDir !== null) {
            return rtrim($outputDir, '/') . "/postman/{$filename}";
        }

        $configPath = config('api-docs.output.postman_path', storage_path('app/collections'));

        return "{$configPath}/{$filename}";
    }

    private function getOpenApiOutputPath(?string $outputDir, string $format): string
    {
        $timestamp = time();
        $extension = $format === 'json' ? 'json' : 'yaml';
        $filename = "{$timestamp}-openapi.{$extension}";

        if ($outputDir !== null) {
            return rtrim($outputDir, '/') . "/openapi/{$filename}";
        }

        $configPath = config('api-docs.output.openapi_path', storage_path('app/openapi'));

        return "{$configPath}/{$filename}";
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * @param  array<int, \ApiDocs\Data\RequestData>  $requests
     */
    private function countRequests(array $requests): int
    {
        return count($requests);
    }

    /**
     * @param  array<int, \ApiDocs\Data\RequestData>  $requests
     */
    private function showFolderSummary(array $requests): void
    {
        $folders = collect($requests)->groupBy('folder')->keys()->sort();

        $this->info('Endpoints by folder:');

        foreach ($folders as $folder) {
            $count = collect($requests)->where('folder', $folder)->count();
            $this->line("  - {$folder} ({$count} endpoints)");
        }
    }
}
