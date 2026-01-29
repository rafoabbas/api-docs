<?php

declare(strict_types=1);

use ApiDocs\Collectors\YamlCollector;
use ApiDocs\Data\RequestData;

beforeEach(function () {
    $this->tempPath = sys_get_temp_dir().'/api-docs-test-'.uniqid();
    mkdir($this->tempPath, 0755, true);
});

afterEach(function () {
    // Clean up temp files
    if (is_dir($this->tempPath)) {
        $files = glob($this->tempPath.'/*.yaml');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->tempPath);
    }
});

it('returns empty array when directory does not exist', function () {
    $collector = new YamlCollector('/non/existent/path');

    expect($collector->collect())->toBeArray()->toBeEmpty();
});

it('returns empty array when directory is empty', function () {
    $collector = new YamlCollector($this->tempPath);

    expect($collector->collect())->toBeArray()->toBeEmpty();
});

it('parses yaml file and returns request data', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Test Request
    method: GET
    uri: /api/test
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests)->toHaveCount(1);
    expect($requests[0])->toBeInstanceOf(RequestData::class);
    expect($requests[0]->name)->toBe('Test Request');
    expect($requests[0]->method)->toBe('GET');
    expect($requests[0]->uri)->toBe('api/test');
    expect($requests[0]->folder)->toBe('Test');
});

it('parses multiple requests from yaml', function () {
    $yaml = <<<'YAML'
folder: Users
requests:
  - name: List Users
    method: GET
    uri: /api/users
  - name: Create User
    method: POST
    uri: /api/users
  - name: Get User
    method: GET
    uri: /api/users/{id}
YAML;

    file_put_contents($this->tempPath.'/users.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests)->toHaveCount(3);
    expect($requests[0]->name)->toBe('List Users');
    expect($requests[1]->name)->toBe('Create User');
    expect($requests[2]->name)->toBe('Get User');
});

it('parses request body from yaml', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Create Item
    method: POST
    uri: /api/items
    body:
      name: Test Item
      price: 100
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests[0]->body)->toBe(['name' => 'Test Item', 'price' => 100]);
});

it('parses auth configuration from yaml', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Protected Request
    method: GET
    uri: /api/protected
    auth:
      type: bearer
      token: "{{MY_TOKEN}}"
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests[0]->auth)->not->toBeNull();
    expect($requests[0]->auth->type)->toBe('bearer');
    expect($requests[0]->auth->token)->toBe('{{MY_TOKEN}}');
});

it('parses responses from yaml', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Get Item
    method: GET
    uri: /api/items/{id}
    responses:
      - name: Success
        status: 200
        body:
          id: 1
          name: Item
      - name: Not Found
        status: 404
        body:
          error: Item not found
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests[0]->responses)->toHaveCount(2);
    expect($requests[0]->responses[0]->name)->toBe('Success');
    expect($requests[0]->responses[0]->status)->toBe(200);
    expect($requests[0]->responses[1]->name)->toBe('Not Found');
    expect($requests[0]->responses[1]->status)->toBe(404);
});

it('parses headers from yaml', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Request with Headers
    method: GET
    uri: /api/test
    headers:
      X-Custom-Header: custom-value
      X-Another: another-value
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests[0]->headers)->toHaveCount(2);
    expect($requests[0]->headers[0]->key)->toBe('X-Custom-Header');
    expect($requests[0]->headers[0]->value)->toBe('custom-value');
});

it('parses query params from yaml', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Search
    method: GET
    uri: /api/search
    query_params:
      q: search term
      page: "1"
      limit: "10"
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests[0]->queryParams)->toHaveCount(3);
    expect($requests[0]->queryParams[0]->key)->toBe('q');
    expect($requests[0]->queryParams[0]->value)->toBe('search term');
});

it('skips invalid yaml files', function () {
    file_put_contents($this->tempPath.'/invalid.yaml', 'not: valid: yaml: content:');

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests)->toBeArray();
});

it('skips requests missing required fields', function () {
    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Valid Request
    method: GET
    uri: /api/valid
  - name: Missing Method
    uri: /api/invalid
  - method: POST
    uri: /api/no-name
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests)->toHaveCount(1);
    expect($requests[0]->name)->toBe('Valid Request');
});

it('can change yaml path via setter', function () {
    $collector = new YamlCollector('/initial/path');
    $collector->setYamlPath($this->tempPath);

    $yaml = <<<'YAML'
folder: Test
requests:
  - name: Test
    method: GET
    uri: /api/test
YAML;

    file_put_contents($this->tempPath.'/test.yaml', $yaml);

    $requests = $collector->collect();

    expect($requests)->toHaveCount(1);
});

it('reads yaml files recursively from subdirectories', function () {
    $subdir = $this->tempPath.'/subdir';
    mkdir($subdir, 0755, true);

    $yaml1 = <<<'YAML'
folder: Root
requests:
  - name: Root Request
    method: GET
    uri: /api/root
YAML;

    $yaml2 = <<<'YAML'
folder: Sub
requests:
  - name: Sub Request
    method: GET
    uri: /api/sub
YAML;

    file_put_contents($this->tempPath.'/root.yaml', $yaml1);
    file_put_contents($subdir.'/sub.yaml', $yaml2);

    $collector = new YamlCollector($this->tempPath);
    $requests = $collector->collect();

    expect($requests)->toHaveCount(2);

    // Clean up subdir
    @unlink($subdir.'/sub.yaml');
    @rmdir($subdir);
});
