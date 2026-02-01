<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
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

    config(['api-docs.yaml_path' => $this->yamlPath]);
});

afterEach(function (): void {
    if (property_exists($this, 'yamlPath') && $this->yamlPath !== null && is_dir($this->yamlPath)) {
        File::deleteDirectory($this->yamlPath);
    }
});

it('serves swagger ui page', function (): void {
    $response = $this->get('/api/docs');

    $response->assertStatus(200);
    $response->assertSee('swagger-ui');
    $response->assertSee('SwaggerUIBundle');
});

it('serves openapi json endpoint', function (): void {
    $response = $this->get('/api/docs/openapi.json');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');

    $json = $response->json();

    expect($json)->toHaveKey('openapi');
    expect($json)->toHaveKey('info');
    expect($json)->toHaveKey('paths');
    expect($json['openapi'])->toBe('3.0.3');
});

it('includes cors headers on openapi endpoint', function (): void {
    $response = $this->get('/api/docs/openapi.json');

    $response->assertHeader('Access-Control-Allow-Origin', '*');
});

it('handles options preflight request', function (): void {
    $response = $this->options('/api/docs/openapi.json');

    $response->assertStatus(200);
    $response->assertHeader('Access-Control-Allow-Origin', '*');
    $response->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
});

it('config has swagger enabled option', function (): void {
    expect(config('api-docs.swagger.enabled'))->toBeBool();
});

it('config has swagger path option', function (): void {
    expect(config('api-docs.swagger.path'))->toBeString();
});

it('includes yaml requests in openapi spec', function (): void {
    $response = $this->get('/api/docs/openapi.json');

    $json = $response->json();

    expect($json['paths'])->toHaveKey('/api/test');
    expect($json['paths']['/api/test'])->toHaveKey('get');
    expect($json['paths']['/api/test']['get']['summary'])->toBe('Test Request');
});

it('sets dark mode in swagger ui', function (): void {
    config(['api-docs.swagger.dark_mode' => true]);

    $response = $this->get('/api/docs');

    $response->assertSee('background-color: #1a1a1a');
});

it('can disable dark mode', function (): void {
    config(['api-docs.swagger.dark_mode' => false]);

    $response = $this->get('/api/docs');

    $response->assertDontSee('background-color: #1a1a1a');
});

it('uses api title from config', function (): void {
    config(['api-docs.openapi.title' => 'My Custom API']);

    $response = $this->get('/api/docs/openapi.json');

    $json = $response->json();

    expect($json['info']['title'])->toBe('My Custom API');
});

it('uses default server url', function (): void {
    $response = $this->get('/api/docs/openapi.json');

    $json = $response->json();

    expect($json['servers'])->toBeArray();
    expect($json['servers'])->not->toBeEmpty();
});
