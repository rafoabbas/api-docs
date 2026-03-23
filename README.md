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

### Option 1: YAML Definitions (Recommended)

Define API endpoints in YAML files under `resources/api-docs/`:

```yaml
# resources/api-docs/auth.yaml
folder: V1 / Auth

auth:
  type: bearer

requests:
  - name: Login
    method: POST
    uri: /v1/auth/login
    description: Authenticate user
    auth:
      type: noauth
    body:
      phone: "905551234567"
      password: "secret123"
    variables:
      - name: BEARER_TOKEN
        path: data.token

  - name: Get Profile
    method: GET
    uri: /v1/auth/me
    resource: App\Http\Resources\UserResource
```

### Option 2: PHP Attributes

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

### Generate Documentation

```bash
php artisan api:generate
```

Output (when `variable_scope` is `collection`):
```
docs/
├── postman/
│   └── {timestamp}-collection.json
└── openapi/
    └── {timestamp}-openapi.yaml
```

Output (when `variable_scope` is `environment`):
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
| `ApiDeprecated` | Mark endpoints as deprecated |

## Auto-Resolve Features

- **Request body** from FormRequest `rules()`
- **Query parameters** from FormRequest `rules()` (for GET/DELETE requests)
- **Response structure** from Resource `toArray()`
- **Authentication** from middleware (`auth:sanctum`, `auth`)
- **Route parameters** from URI (`{id}` → `:id`)
- **OpenAPI schema** from validation rules (`required|email` → `type: string, format: email`)
- **PHP Enum** to OpenAPI enum conversion (via `Rule::in()` or `Enum` rule)
- **File upload** auto-detection (`file`, `image`, `mimes:` → `multipart/form-data`)
- **Pagination** auto-detection (`paginate()` → paginated response schema)

## Merging Strategy

YAML and PHP attributes can be used together. When both define the same endpoint (matched by `method + uri`):

- **YAML definitions take priority** over PHP attributes
- Merge is done field-by-field (non-null YAML fields override attribute fields)
- Unmatched requests from both sources are included
- YAML `body_merge` and `body_except` allow merging YAML body with auto-resolved FormRequest body

This allows a YAML-first workflow where controllers stay clean and all API documentation lives in YAML files.

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
    'token' => env('API_DOCS_SWAGGER_TOKEN'),
],
```

**Endpoints:**
- `/api/docs` - Swagger UI interface
- `/api/docs/openapi.json` - OpenAPI specification

**Protect with token:**
```env
API_DOCS_SWAGGER_TOKEN=your-secret-token
```

Access: `/api/docs?token=your-secret-token`

**Disable in production:**
```env
API_DOCS_SWAGGER_ENABLED=false
```

## Documentation

Full documentation is available on the **[Wiki](https://github.com/rafoabbas/api-docs/wiki)**:

- [Configuration](https://github.com/rafoabbas/api-docs/wiki/Configuration)
- [Attributes](https://github.com/rafoabbas/api-docs/wiki/Attributes)
- [YAML Definitions](https://github.com/rafoabbas/api-docs/wiki/YAML-Definitions)
- [Auto-Resolve](https://github.com/rafoabbas/api-docs/wiki/Auto-Resolve)

## Roadmap

### Completed
- [x] Swagger UI integration - Interactive docs at `/api/docs`
- [x] Query parameter auto-resolve from FormRequest for GET/DELETE requests
- [x] Class-level `ApiPreRequest` support
- [x] YAML-first workflow with priority merge
- [x] YAML `resource` support for auto-resolving response from Resource classes
- [x] YAML `body_merge` / `body_except` for merging with FormRequest body
- [x] YAML `hidden` support to exclude requests from docs
- [x] YAML file-level shared `auth`, `headers`, `pre_request_scripts`
- [x] Configurable `variable_scope` (`collection` or `environment`)
- [x] Validation rules to OpenAPI schema (`required|email` → `type: string, format: email`)
- [x] PHP Enum to OpenAPI enum conversion (via `Rule::in()` or `Enum` validation rule)
- [x] `#[ApiDeprecated]` - Mark endpoints as deprecated (with reason and replacement)
- [x] Pagination auto-detect (`paginate()` → paginated response schema with links/meta)
- [x] File upload - Auto multipart/form-data support (`file`, `image`, `mimes:` rules)

### Export Formats
- [ ] Markdown export
- [ ] Insomnia export format
- [ ] Bruno export format
- [ ] Postman Cloud sync

### Schema & Validation
- [ ] JSON:API specification support

### Attributes
- [ ] `#[ApiRateLimit]` - Document rate limits
- [ ] `#[ApiWebhook]` - Webhook documentation

### Auto-Detection
- [ ] Factory examples - Generate example data from Laravel factories

### Authentication
- [ ] OAuth2 flows documentation
- [ ] Multiple auth schemes per endpoint

### UI & Visualization
- [ ] ReDoc UI - Alternative documentation UI
- [ ] Scalar UI - Modern API documentation (scalar.com)
- [ ] Code snippets - curl, JavaScript, Python, PHP examples
- [ ] API changelog - Version diff documentation

### Versioning
- [ ] API versioning - Separate specs for v1, v2, etc.

## License

MIT