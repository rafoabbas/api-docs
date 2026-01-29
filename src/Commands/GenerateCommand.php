<?php

declare(strict_types=1);

namespace ApiDocs\Commands;

use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Generators\CollectionGenerator;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    protected $signature = 'postman:generate
                            {--output= : Output file path (default: storage/app/collections/{timestamp}-collection.json)}
                            {--name= : Collection name (default: app name)}
                            {--exclude=* : Prefixes to exclude (e.g., --exclude=admin --exclude=internal)}';

    protected $description = 'Generate a Postman collection from PHP attributes on controllers';

    public function __construct(
        private readonly AttributeCollector $collector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Collecting routes with attributes...');

        $excludePrefixes = $this->option('exclude');
        if (is_array($excludePrefixes) && count($excludePrefixes) > 0) {
            $defaultPrefixes = ['_', 'sanctum', 'telescope', 'storage', 'mcp'];
            $this->collector->setExcludePrefixes(array_merge($defaultPrefixes, $excludePrefixes));
        }

        $requests = $this->collector->collect();

        if (count($requests) === 0) {
            $this->error('No routes found.');

            return self::FAILURE;
        }

        $this->info('Found '.count($requests).' routes.');

        $collectionName = $this->option('name') ?? config('app.name', 'API');
        $generator = new CollectionGenerator($collectionName);

        // Configure variables from config
        $this->configureVariables($generator);

        $collection = $generator->generate($requests);

        $outputPath = $this->option('output') ?? $this->generateOutputPath();

        // Ensure directory exists
        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Postman collection generated: {$outputPath}");
        $this->info('Total requests: '.count($requests));

        // Show folder summary
        $folders = collect($requests)->groupBy('folder')->keys()->sort();
        $this->newLine();
        $this->info('Folders:');
        foreach ($folders as $folder) {
            $count = collect($requests)->where('folder', $folder)->count();
            $this->line("  - {$folder} ({$count} requests)");
        }

        return self::SUCCESS;
    }

    private function generateOutputPath(): string
    {
        $timestamp = time();
        $filename = "{$timestamp}-collection.json";

        return storage_path("app/collections/{$filename}");
    }

    private function configureVariables(CollectionGenerator $generator): void
    {
        $apiDomain = config('app.api_domain');
        if (! $apiDomain) {
            $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $apiDomain = "api.{$host}";
        }

        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';

        $generator->setVariables([
            'API_URL' => "{$scheme}://{$apiDomain}",
            'API_URL_V1' => "{$scheme}://{$apiDomain}/v1",
            'BEARER_TOKEN' => '',
            'PHONE' => '905050414874',
            'OTP_CODE' => '',
            'ORDER_UUID' => '',
        ]);

        // Add custom variables from config if available
        $customVariables = config('api-docs.variables', []);
        foreach ($customVariables as $key => $value) {
            $generator->addVariable($key, $value);
        }
    }
}
