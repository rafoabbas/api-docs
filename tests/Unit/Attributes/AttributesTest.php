<?php

declare(strict_types=1);

use ApiDocs\Attributes\ApiAuth;
use ApiDocs\Attributes\ApiBody;
use ApiDocs\Attributes\ApiFolder;
use ApiDocs\Attributes\ApiHeader;
use ApiDocs\Attributes\ApiHidden;
use ApiDocs\Attributes\ApiPreRequest;
use ApiDocs\Attributes\ApiQueryParam;
use ApiDocs\Attributes\ApiRequest;
use ApiDocs\Attributes\ApiResource;
use ApiDocs\Attributes\ApiResponse;
use ApiDocs\Attributes\ApiTest;
use ApiDocs\Attributes\ApiVariable;

describe('ApiFolder', function (): void {
    it('stores folder name', function (): void {
        $attr = new ApiFolder('Users / List');

        expect($attr->name)->toBe('Users / List');
    });

    it('is a valid attribute', function (): void {
        $reflection = new ReflectionClass(ApiFolder::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->not->toBeEmpty();
    });
});

describe('ApiRequest', function (): void {
    it('stores request details', function (): void {
        $attr = new ApiRequest(
            name: 'Get Users',
            description: 'Retrieve all users',
            order: 1,
        );

        expect($attr->name)->toBe('Get Users');
        expect($attr->description)->toBe('Retrieve all users');
        expect($attr->order)->toBe(1);
    });

    it('has default values', function (): void {
        $attr = new ApiRequest(name: 'Test');

        expect($attr->name)->toBe('Test');
        expect($attr->description)->toBeNull();
        expect($attr->order)->toBe(0);
    });
});

describe('ApiBody', function (): void {
    it('stores body data', function (): void {
        $attr = new ApiBody(['name' => 'John', 'email' => 'john@example.com']);

        expect($attr->data)->toBe(['name' => 'John', 'email' => 'john@example.com']);
    });

    it('has merge and except options', function (): void {
        $attr = new ApiBody(
            data: ['field' => 'value'],
            merge: true,
            except: ['password'],
        );

        expect($attr->merge)->toBeTrue();
        expect($attr->except)->toBe(['password']);
    });

    it('has mode and language options', function (): void {
        $attr = new ApiBody(
            data: [],
            mode: 'formdata',
            language: 'text',
        );

        expect($attr->mode)->toBe('formdata');
        expect($attr->language)->toBe('text');
    });
});

describe('ApiResponse', function (): void {
    it('stores response details', function (): void {
        $attr = new ApiResponse(
            name: 'Success',
            status: 200,
            body: ['id' => 1],
            headers: ['X-Custom' => 'value'],
        );

        expect($attr->name)->toBe('Success');
        expect($attr->status)->toBe(200);
        expect($attr->body)->toBe(['id' => 1]);
        expect($attr->headers)->toBe(['X-Custom' => 'value']);
    });
});

describe('ApiAuth', function (): void {
    it('stores auth type', function (): void {
        $attr = new ApiAuth(type: 'bearer');

        expect($attr->type)->toBe('bearer');
    });

    it('stores bearer token', function (): void {
        $attr = new ApiAuth(type: 'bearer', token: '{{MY_TOKEN}}');

        expect($attr->token)->toBe('{{MY_TOKEN}}');
    });

    it('stores basic auth credentials', function (): void {
        $attr = new ApiAuth(type: 'basic', username: 'admin', password: 'secret');

        expect($attr->username)->toBe('admin');
        expect($attr->password)->toBe('secret');
    });

    it('stores api key auth', function (): void {
        $attr = new ApiAuth(type: 'apikey', apiKey: 'my-key', apiKeyHeader: 'X-API-Key');

        expect($attr->apiKey)->toBe('my-key');
        expect($attr->apiKeyHeader)->toBe('X-API-Key');
    });
});

describe('ApiHeader', function (): void {
    it('stores header details', function (): void {
        $attr = new ApiHeader(
            key: 'X-Custom-Header',
            value: 'custom-value',
            description: 'A custom header',
            disabled: false,
        );

        expect($attr->key)->toBe('X-Custom-Header');
        expect($attr->value)->toBe('custom-value');
        expect($attr->description)->toBe('A custom header');
        expect($attr->disabled)->toBeFalse();
    });
});

describe('ApiQueryParam', function (): void {
    it('stores query param details', function (): void {
        $attr = new ApiQueryParam(
            key: 'page',
            value: '1',
            description: 'Page number',
            disabled: false,
        );

        expect($attr->key)->toBe('page');
        expect($attr->value)->toBe('1');
        expect($attr->description)->toBe('Page number');
        expect($attr->disabled)->toBeFalse();
    });
});

describe('ApiVariable', function (): void {
    it('stores variable details', function (): void {
        $attr = new ApiVariable(
            name: 'TOKEN',
            path: 'data.token',
            scope: 'collection',
        );

        expect($attr->name)->toBe('TOKEN');
        expect($attr->path)->toBe('data.token');
        expect($attr->scope)->toBe('collection');
    });
});

describe('ApiResource', function (): void {
    it('stores resource class', function (): void {
        $attr = new ApiResource(
            resourceClass: 'App\Http\Resources\UserResource',
            wrapped: true,
            status: 200,
        );

        expect($attr->resourceClass)->toBe('App\Http\Resources\UserResource');
        expect($attr->status)->toBe(200);
        expect($attr->wrapped)->toBeTrue();
    });
});

describe('ApiTest', function (): void {
    it('stores test script', function (): void {
        $attr = new ApiTest(
            script: 'pm.response.to.have.status(200);',
            name: 'Status should be 200',
        );

        expect($attr->script)->toBe('pm.response.to.have.status(200);');
        expect($attr->name)->toBe('Status should be 200');
    });
});

describe('ApiPreRequest', function (): void {
    it('stores pre-request script', function (): void {
        $attr = new ApiPreRequest('console.log("Running...");');

        expect($attr->script)->toBe('console.log("Running...");');
    });
});

describe('ApiHidden', function (): void {
    it('can be instantiated', function (): void {
        $attr = new ApiHidden;

        expect($attr)->toBeInstanceOf(ApiHidden::class);
    });
});
