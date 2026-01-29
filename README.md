# API Docs

Generate API documentation (Postman collections & OpenAPI specs) from PHP 8 attributes and YAML files.

## Requirements

- PHP 8.2+
- Laravel 11+

## Installation

```bash
composer require rafoabbas/api-docs
```

## Quick Start

Add attributes to your controller methods:

```php
use ApiDocs\Attributes\ApiFolder;
use ApiDocs\Attributes\ApiRequest;

#[ApiFolder('V1 / Customer / Auth')]
class AuthController extends Controller
{
    #[ApiRequest(name: 'Login', description: 'Authenticate user')]
    public function login(LoginRequest $request): JsonResponse
    {
        // ...
    }
}
```

Generate documentation:

```bash
php artisan api:generate
```

Output files:
- Postman collection: `storage/app/collections/{timestamp}-collection.json`
- OpenAPI spec: `storage/app/openapi/{timestamp}-openapi.yaml`

## Command Options

```bash
# Generate only Postman collection
php artisan api:generate --format=postman

# Generate only OpenAPI spec
php artisan api:generate --format=openapi

# Generate both (default)
php artisan api:generate --format=both

# Custom output directory
php artisan api:generate --output=docs

# Custom collection/API name
php artisan api:generate --name="My API"

# OpenAPI output as JSON instead of YAML
php artisan api:generate --openapi-format=json

# Custom YAML definitions path
php artisan api:generate --yaml=resources/api

# Exclude route prefixes
php artisan api:generate --exclude=admin --exclude=internal
```

## Attributes

### ApiRequest

Define request name, description, and order within folder.

```php
#[ApiRequest(
    name: 'Create User',
    description: 'Create a new user account',
    order: 1
)]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `?string` | auto | Request name |
| `description` | `?string` | `null` | Request description |
| `order` | `int` | `0` | Order within folder (lower = first) |

### ApiFolder

Group requests into folders. Can be applied to class or method.

```php
#[ApiFolder('V1 / Customer / Auth')]
class AuthController extends Controller
```

Nested folders are created using `/` separator.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string` | required | Folder name |
| `description` | `?string` | `null` | Folder description |
| `order` | `int` | `0` | Folder order (lower = first) |

### ApiBody

Define request body. Supports merging with auto-resolved FormRequest fields.

```php
// Replace entirely (ignore FormRequest)
#[ApiBody(['email' => 'test@example.com', 'password' => 'secret'])]

// Merge with FormRequest (ApiBody values override)
#[ApiBody(
    data: ['status' => 'active'],
    merge: true
)]

// Merge with FormRequest, but exclude certain fields
#[ApiBody(
    data: ['service_type' => 'towing'],
    merge: true,
    except: ['uuid', 'user_id', 'created_at']
)]

// Form data mode
#[ApiBody(['file' => 'image.jpg'], mode: 'formdata')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `data` | `array` | `[]` | Request body data |
| `mode` | `string` | `'raw'` | Body mode: `raw`, `formdata`, `urlencoded` |
| `language` | `string` | `'json'` | Language for raw mode |
| `merge` | `bool` | `false` | Merge with auto-resolved FormRequest body |
| `except` | `array` | `[]` | Keys to exclude when merging |

### ApiResource

Specify the response Resource class for auto-resolving response structure.

```php
#[ApiResource(UserResource::class)]
#[ApiResource(UserResource::class, status: 201)]
#[ApiResource(UserResource::class, wrapped: false)]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `resourceClass` | `string` | required | Resource class name |
| `status` | `int` | `200` | HTTP status code |
| `wrapped` | `bool` | `true` | Wrap in standard API response |

### ApiVariable

Extract values from response and save to Postman variables.

```php
#[ApiVariable('BEARER_TOKEN', path: 'data.token')]
#[ApiVariable('USER_ID', path: 'data.user.id', scope: 'environment')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string` | required | Variable name |
| `path` | `string` | required | JSON path to extract |
| `scope` | `string` | `'collection'` | Scope: `collection`, `environment`, `global` |

### ApiHeader

Add custom headers to requests.

```php
#[ApiHeader('X-Custom-Header', 'value')]
#[ApiHeader('X-Debug', 'true', description: 'Enable debug mode', disabled: true)]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | `string` | required | Header key |
| `value` | `string` | required | Header value |
| `description` | `?string` | `null` | Header description |
| `disabled` | `bool` | `false` | Disabled by default |

### ApiQueryParam

Add query parameters to requests.

```php
#[ApiQueryParam('page', '1')]
#[ApiQueryParam('per_page', '15', description: 'Items per page')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | `string` | required | Parameter key |
| `value` | `string` | `''` | Parameter value |
| `description` | `?string` | `null` | Parameter description |
| `disabled` | `bool` | `false` | Disabled by default |

### ApiAuth

Configure authentication for requests. Can be applied to class or method.

```php
// Bearer token (default)
#[ApiAuth]
#[ApiAuth(type: 'bearer', token: '{{BEARER_TOKEN}}')]

// Basic auth
#[ApiAuth(type: 'basic', username: 'user', password: 'pass')]

// API key
#[ApiAuth(type: 'apikey', apiKey: '{{API_KEY}}', apiKeyHeader: 'X-API-Key')]

// No auth
#[ApiAuth(type: 'noauth')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'bearer'` | Auth type: `bearer`, `basic`, `apikey`, `noauth` |
| `token` | `?string` | `'{{BEARER_TOKEN}}'` | Token for bearer auth |
| `username` | `?string` | `null` | Username for basic auth |
| `password` | `?string` | `null` | Password for basic auth |
| `apiKey` | `?string` | `null` | API key value |
| `apiKeyHeader` | `?string` | `'X-API-Key'` | API key header name |

### ApiResponse

Define example responses manually.

```php
#[ApiResponse(
    name: 'Success',
    status: 200,
    body: ['success' => true, 'data' => ['id' => 1]]
)]
#[ApiResponse(
    name: 'Validation Error',
    status: 422,
    body: ['message' => 'The email field is required.']
)]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string` | required | Response example name |
| `status` | `int` | `200` | HTTP status code |
| `body` | `array` | `[]` | Response body |
| `headers` | `array` | `[]` | Response headers |

### ApiTest

Add Postman test scripts.

```php
#[ApiTest('pm.response.to.have.status(200);', name: 'Status is 200')]
#[ApiTest('pm.expect(pm.response.json().success).to.be.true;')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `script` | `string` | required | JavaScript test script |
| `name` | `?string` | `null` | Test name |

### ApiPreRequest

Add pre-request scripts.

```php
#[ApiPreRequest('pm.variables.set("timestamp", Date.now());')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `script` | `string` | required | JavaScript pre-request script |

### ApiHidden

Exclude a controller or method from the documentation.

```php
#[ApiHidden]
class InternalController extends Controller

// Or on a specific method
#[ApiHidden]
public function debugEndpoint()
```

## Postman Environments

Generate separate environment files for different stages:

```php
// config/api-docs.php
'environments' => [
    'local' => [
        'API_URL' => 'http://localhost:8000',
        'BEARER_TOKEN' => '',
    ],
    'staging' => [
        'API_URL' => 'https://staging-api.example.com',
        'BEARER_TOKEN' => '',
    ],
    'production' => [
        'API_URL' => 'https://api.example.com',
        'BEARER_TOKEN' => '',
    ],
],
```

Running `php artisan api:generate` will create:
- `{timestamp}-collection.json`
- `{timestamp}-local.postman_environment.json`
- `{timestamp}-staging.postman_environment.json`
- `{timestamp}-production.postman_environment.json`

Import these environment files into Postman and switch between them easily.

## YAML Definitions

Define API endpoints in YAML files placed in `resources/api-docs/`:

```yaml
# resources/api-docs/auth.yaml
folder: V1 / Customer / Auth

requests:
  - name: Login
    method: POST
    uri: /v1/auth/login
    description: Authenticate user and get token
    body:
      phone: "905551234567"
      password: "secret"
    variables:
      - name: BEARER_TOKEN
        path: data.token
    responses:
      - name: Success
        status: 200
        body:
          success: true
          data:
            token: "eyJ..."

  - name: Logout
    method: POST
    uri: /v1/auth/logout
    auth:
      type: bearer
```

YAML definitions are merged with PHP attributes. Attributes take priority over YAML.

## Auto-Resolve Features

### Request Body from FormRequest

If no `ApiBody` attribute is provided, the package automatically extracts fields from the FormRequest's `rules()` method:

```php
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'nullable|integer',
        ];
    }
}

// Controller - body auto-resolved from CreateUserRequest
public function store(CreateUserRequest $request): JsonResponse
```

Generated body:
```json
{
    "name": "Example Name",
    "email": "user@example.com",
    "age": 25
}
```

### Response from Resource

The package auto-detects return patterns and resolves response structure:

```php
// Detected patterns:
return new UserResource($user);
return UserResource::make($user);
return UserResource::collection($users);
return response()->json(['key' => 'value']);
```

Nested Resources are also resolved recursively.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=api-docs-config
```

```php
// config/api-docs.php
return [
    // Route prefixes to exclude
    'exclude_prefixes' => ['_', 'sanctum', 'telescope', 'storage', 'mcp'],

    // Custom Postman variables (collection-level)
    'variables' => [
        'CUSTOM_VAR' => 'value',
    ],

    // Postman environments (generates separate .postman_environment.json files)
    'environments' => [
        'local' => [
            'API_URL' => 'http://localhost:8000',
            'BEARER_TOKEN' => '',
        ],
        'staging' => [
            'API_URL' => 'https://staging-api.example.com',
            'BEARER_TOKEN' => '',
        ],
        'production' => [
            'API_URL' => 'https://api.example.com',
            'BEARER_TOKEN' => '',
        ],
    ],

    // Default headers for all requests
    'default_headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    // Path to YAML definitions
    'yaml_path' => resource_path('api-docs'),

    // OpenAPI settings
    'openapi' => [
        'title' => 'My API',
        'version' => '1.0.0',
        'description' => 'API documentation',
        'servers' => [
            ['url' => 'https://api.example.com', 'description' => 'Production'],
        ],
        'output_format' => 'yaml', // or 'json'
    ],

    // Output paths
    'output' => [
        'postman_path' => storage_path('app/collections'),
        'openapi_path' => storage_path('app/openapi'),
    ],
];
```

## License

MIT