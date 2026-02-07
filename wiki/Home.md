# ApiDocs for Laravel

Generate **Postman Collections**, **OpenAPI 3.x specs**, and **Swagger UI** from PHP attributes and YAML files.

## Features

- **PHP Attributes** - Define API docs directly in controller methods
- **YAML Definitions** - Alternative/supplement to attributes
- **Auto-Resolve** - Request body from FormRequest, response from Resource classes
- **Postman Export** - Collections with variables, tests, pre-request scripts
- **OpenAPI 3.x** - YAML or JSON output
- **Swagger UI** - Built-in interactive documentation with dark mode
- **Multiple Environments** - Local, staging, production

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

## Installation

```bash
composer require rafoabbas/api-docs
```

Publish config:

```bash
php artisan vendor:publish --tag=api-docs-config
```

## Quick Start

### 1. Add Attributes to Controllers

```php
use ApiDocs\Attributes\{ApiFolder, ApiRequest, ApiBody, ApiResource, ApiVariable};

#[ApiFolder('V1 / Auth')]
class AuthController extends Controller
{
    #[ApiRequest(name: 'Login', description: 'Authenticate user')]
    #[ApiBody(['phone' => '905551234567', 'password' => 'secret123'])]
    #[ApiVariable('BEARER_TOKEN', path: 'data.token')]
    public function login(LoginRequest $request): JsonResponse
    {
        // ...
    }
}
```

### 2. Or Use YAML

```yaml
# resources/api-docs/auth.yaml
folder: V1 / Auth

requests:
  - name: Login
    method: POST
    uri: /v1/auth/login
    body:
      phone: "905551234567"
      password: "secret123"
    variables:
      - name: BEARER_TOKEN
        path: data.token
```

### 3. Generate Documentation

```bash
# Both Postman + OpenAPI (default)
php artisan api:generate

# Only Postman
php artisan api:generate --format=postman

# Only OpenAPI
php artisan api:generate --format=openapi
```

### 4. View Swagger UI

Visit `/api/docs` in your browser.

## Documentation

- [[Configuration]]
- [[Attributes]]
- [[YAML Definitions]]
- [[Auto-Resolve]]