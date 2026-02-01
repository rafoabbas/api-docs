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

Add attributes to your controller:

```php
use ApiDocs\Attributes\ApiFolder;
use ApiDocs\Attributes\ApiRequest;

#[ApiFolder('V1 / Auth')]
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

Output:
```
docs/
├── postman/
│   ├── {timestamp}-collection.json
│   └── {timestamp}-local.postman_environment.json
└── openapi/
    └── {timestamp}-openapi.yaml
```

## Command Options

```bash
php artisan api:generate --format=postman      # Only Postman
php artisan api:generate --format=openapi      # Only OpenAPI
php artisan api:generate --format=both         # Both (default)
php artisan api:generate --name="My API"       # Custom name
php artisan api:generate --output=api-docs     # Custom output dir
php artisan api:generate --openapi-format=json # OpenAPI as JSON
php artisan api:generate --exclude=admin       # Exclude prefixes
```

## Attributes

| Attribute | Description |
|-----------|-------------|
| `ApiRequest` | Request name, description, order |
| `ApiFolder` | Group requests into folders |
| `ApiBody` | Request body (supports FormRequest merge) |
| `ApiResource` | Response Resource class |
| `ApiVariable` | Extract response values to variables |
| `ApiHeader` | Custom headers |
| `ApiQueryParam` | Query parameters |
| `ApiAuth` | Authentication config |
| `ApiResponse` | Example responses |
| `ApiTest` | Postman test scripts |
| `ApiPreRequest` | Pre-request scripts |
| `ApiHidden` | Exclude from docs |

## Auto-Resolve Features

- **Request body** from FormRequest `rules()`
- **Response structure** from Resource `toArray()`
- **Authentication** from middleware (`auth:sanctum`, `auth`)
- **Route parameters** from URI (`{id}` → `:id`)

## Swagger UI

Interactive API documentation is available at `/api/docs` by default.

```php
// config/api-docs.php
'swagger' => [
    'enabled' => true,
    'path' => '/api/docs',
    'middleware' => [],
    'dark_mode' => true,
    'persist_authorization' => true,
],
```

**Endpoints:**
- `/api/docs` - Swagger UI interface
- `/api/docs/openapi.json` - OpenAPI specification

**Disable in production:**
```env
API_DOCS_SWAGGER_ENABLED=false
```

## Documentation

- [Attributes](docs/attributes.md) - All attributes with examples
- [Configuration](docs/configuration.md) - Config options
- [YAML Definitions](docs/yaml-definitions.md) - YAML file format
- [Auto-Resolve](docs/auto-resolve.md) - Auto-detection features

## Roadmap

- [x] Swagger UI integration - Interactive docs at `/api/docs`
- [ ] Markdown export
- [ ] Validation rules to OpenAPI schema (`required|email` → `type: string, format: email`)
- [ ] `#[ApiDeprecated]` attribute
- [ ] Pagination auto-detect (`LengthAwarePaginator` response)
- [ ] PHP Enum to OpenAPI enum conversion
- [ ] Insomnia/Bruno export formats

## License

MIT