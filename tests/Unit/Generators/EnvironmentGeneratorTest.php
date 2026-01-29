<?php

declare(strict_types=1);

use ApiDocs\Generators\EnvironmentGenerator;

it('generates valid postman environment structure', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('local', [
        'API_URL' => 'http://localhost:8000',
    ]);

    expect($environment)->toHaveKey('id');
    expect($environment)->toHaveKey('name');
    expect($environment)->toHaveKey('values');
    expect($environment)->toHaveKey('_postman_variable_scope');
    expect($environment['_postman_variable_scope'])->toBe('environment');
});

it('sets environment name correctly', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('production', []);

    expect($environment['name'])->toBe('production');
});

it('generates unique id for each environment', function () {
    $generator = new EnvironmentGenerator;
    $env1 = $generator->generate('local', []);
    $env2 = $generator->generate('staging', []);

    expect($env1['id'])->not->toBe($env2['id']);
});

it('converts variables to postman format', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('local', [
        'API_URL' => 'http://localhost:8000',
        'BEARER_TOKEN' => 'test_token',
    ]);

    expect($environment['values'])->toHaveCount(2);
    expect($environment['values'][0])->toHaveKey('key');
    expect($environment['values'][0])->toHaveKey('value');
    expect($environment['values'][0])->toHaveKey('type');
    expect($environment['values'][0])->toHaveKey('enabled');
});

it('sets variable values correctly', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('local', [
        'API_URL' => 'http://localhost:8000',
        'BEARER_TOKEN' => '',
    ]);

    $apiUrl = collect($environment['values'])->firstWhere('key', 'API_URL');
    $token = collect($environment['values'])->firstWhere('key', 'BEARER_TOKEN');

    expect($apiUrl['value'])->toBe('http://localhost:8000');
    expect($token['value'])->toBe('');
});

it('enables all variables by default', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('local', [
        'VAR1' => 'value1',
        'VAR2' => 'value2',
    ]);

    foreach ($environment['values'] as $variable) {
        expect($variable['enabled'])->toBeTrue();
    }
});

it('sets variable type to default', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('local', [
        'API_URL' => 'http://localhost',
    ]);

    expect($environment['values'][0]['type'])->toBe('default');
});

it('handles empty variables array', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('empty', []);

    expect($environment['values'])->toBeArray();
    expect($environment['values'])->toBeEmpty();
});

it('includes export metadata', function () {
    $generator = new EnvironmentGenerator;
    $environment = $generator->generate('local', []);

    expect($environment)->toHaveKey('_postman_exported_at');
    expect($environment)->toHaveKey('_postman_exported_using');
    expect($environment['_postman_exported_using'])->toBe('ApiDocs/1.0');
});