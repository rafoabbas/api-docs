<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Create temp directories for output
    $this->outputPath = sys_get_temp_dir() . '/api-docs-output-' . uniqid();
    mkdir($this->outputPath . '/postman', 0755, true);
    mkdir($this->outputPath . '/openapi', 0755, true);

    // Create yaml fixtures directory
    $this->yamlPath = sys_get_temp_dir() . '/api-docs-yaml-' . uniqid();
    mkdir($this->yamlPath, 0755, true);

    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Test Request
    method: GET
    uri: /api/test
    description: A test request
    responses:
      - name: Success
        status: 200
        body:
          message: success
YAML;

    file_put_contents($this->yamlPath . '/test.yaml', $yaml);
});

afterEach(function (): void {
    // Clean up
    if (property_exists($this, 'outputPath') && $this->outputPath !== null && is_dir($this->outputPath)) {
        File::deleteDirectory($this->outputPath);
    }

    if (property_exists($this, 'yamlPath') && $this->yamlPath !== null && is_dir($this->yamlPath)) {
        File::deleteDirectory($this->yamlPath);
    }
});

it('has the api:generate command registered', function (): void {
    $this->artisan('api:generate --help')
        ->assertSuccessful();
});

it('accepts format option', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'postman',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();
});

it('rejects invalid format option', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'invalid',
        '--yaml' => $this->yamlPath,
    ])->assertFailed();
});

it('generates postman collection when format is postman', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'postman',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $files = glob($this->outputPath . '/postman/*-collection.json');

    expect($files)->not->toBeEmpty();

    $content = file_get_contents($files[0]);
    $json = json_decode($content, true);

    expect($json)->toHaveKey('info');
    expect($json)->toHaveKey('item');
    expect($json['info']['schema'])->toBe('https://schema.getpostman.com/json/collection/v2.1.0/collection.json');
});

it('generates openapi spec when format is openapi', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'openapi',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $yamlFiles = glob($this->outputPath . '/openapi/*-openapi.yaml');
    $jsonFiles = glob($this->outputPath . '/openapi/*-openapi.json');

    expect(count($yamlFiles) + count($jsonFiles))->toBeGreaterThan(0);
});

it('generates both formats when format is both', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'both',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $postmanFiles = glob($this->outputPath . '/postman/*-collection.json');
    $openapiFiles = glob($this->outputPath . '/openapi/*-openapi.*');

    expect($postmanFiles)->not->toBeEmpty();
    expect($openapiFiles)->not->toBeEmpty();
});

it('can output openapi as json', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'openapi',
        '--openapi-format' => 'json',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $files = glob($this->outputPath . '/openapi/*-openapi.json');

    expect($files)->not->toBeEmpty();

    $content = file_get_contents($files[0]);
    $json = json_decode($content, true);

    expect($json['openapi'])->toBe('3.0.3');
});

it('can output openapi as yaml', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'openapi',
        '--openapi-format' => 'yaml',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $files = glob($this->outputPath . '/openapi/*-openapi.yaml');

    expect($files)->not->toBeEmpty();

    $content = file_get_contents($files[0]);

    expect($content)->toContain('openapi: 3.0.3');
});

it('reads yaml files from specified path', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'postman',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $files = glob($this->outputPath . '/postman/*-collection.json');
    $content = file_get_contents($files[0]);
    $json = json_decode($content, true);

    // Find the test request in the collection
    $found = false;

    foreach ($json['item'] as $folder) {
        if (isset($folder['item'])) {
            foreach ($folder['item'] as $item) {
                if (isset($item['name']) && $item['name'] === 'Test Request') {
                    $found = true;
                    break 2;
                }
            }
        }
    }

    expect($found)->toBeTrue();
});

it('uses custom collection name', function (): void {
    $this->artisan('api:generate', [
        '--format' => 'postman',
        '--name' => 'My Custom API',
        '--yaml' => $this->yamlPath,
        '--output' => $this->outputPath,
    ])->assertSuccessful();

    $files = glob($this->outputPath . '/postman/*-collection.json');
    $content = file_get_contents($files[0]);
    $json = json_decode($content, true);

    expect($json['info']['name'])->toBe('My Custom API');
});
