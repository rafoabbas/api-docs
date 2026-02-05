# Attributes

All attributes are in the `ApiDocs\Attributes` namespace.

## ApiRequest

Define request metadata. Applied to controller methods.

```php
use ApiDocs\Attributes\ApiRequest;

#[ApiRequest(
    name: 'Create User',
    description: 'Create a new user account',
    order: 1
)]
public function store(CreateUserRequest $request): JsonResponse
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `?string` | auto-generated | Request name in documentation |
| `description` | `?string` | `null` | Request description |
| `order` | `int` | `0` | Order within folder (lower = first) |

If `name` is not provided, it will be auto-generated from the method name.

---

## ApiFolder

Group requests into folders. Can be applied to class or method.

```php
use ApiDocs\Attributes\ApiFolder;

#[ApiFolder('V1 / Customer / Auth')]
class AuthController extends Controller
{
    #[ApiFolder('V1 / Customer / Auth / OTP')]  // Override class-level folder
    public function verifyOtp(): JsonResponse
}
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string` | required | Folder name |
| `description` | `?string` | `null` | Folder description |
| `order` | `int` | `0` | Folder order (lower = first) |

Nested folders are created using `/` separator: `V1 / Customer / Auth` creates:
```
V1/
└── Customer/
    └── Auth/
        └── [requests]
```

---

## ApiBody

Define request body. Supports merging with auto-resolved FormRequest fields.

```php
use ApiDocs\Attributes\ApiBody;

// Replace entirely (ignore FormRequest)
#[ApiBody(['email' => 'test@example.com', 'password' => 'secret'])]

// Merge with FormRequest (ApiBody values override)
#[ApiBody(
    data: ['status' => 'active'],
    merge: true
)]

// Merge but exclude certain fields
#[ApiBody(
    data: ['service_type' => 'towing'],
    merge: true,
    except: ['uuid', 'user_id', 'created_at']
)]

// Form data mode (for file uploads)
#[ApiBody(['file' => 'image.jpg'], mode: 'formdata')]

// URL encoded mode
#[ApiBody(['grant_type' => 'password'], mode: 'urlencoded')]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `data` | `array` | `[]` | Request body data |
| `mode` | `string` | `'raw'` | Body mode: `raw`, `formdata`, `urlencoded` |
| `language` | `string` | `'json'` | Language for raw mode: `json`, `xml`, `text` |
| `merge` | `bool` | `false` | Merge with auto-resolved FormRequest body |
| `except` | `array` | `[]` | Keys to exclude when merging |

### Merge Behavior

When `merge: true`:
1. Package extracts fields from FormRequest's `rules()` method
2. Generates example values based on field names and validation rules
3. Merges with `data` array (data values take priority)
4. Removes fields listed in `except`

---

## ApiResource

Specify the response Resource class for auto-resolving response structure.

```php
use ApiDocs\Attributes\ApiResource;

#[ApiResource(UserResource::class)]
public function show(User $user): JsonResponse

#[ApiResource(UserResource::class, status: 201)]
public function store(Request $request): JsonResponse

#[ApiResource(UserResource::class, wrapped: false)]
public function profile(): JsonResponse

#[ApiResource(UserResource::class, collection: true)]
public function index(): JsonResponse
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `resourceClass` | `string` | required | Fully qualified Resource class name |
| `status` | `int` | `200` | HTTP status code for the response |
| `wrapped` | `?bool` | `null` | Wrap in standard API response format (auto-detected if null) |
| `collection` | `?bool` | `null` | Return as array/collection (auto-detected if null) |

### Wrapped Response Format

When `wrapped: true` (default):
```json
{
    "success": true,
    "status_code": 200,
    "message": null,
    "data": { /* resource fields */ }
}
```

When `wrapped: false`:
```json
{ /* resource fields only */ }
```

---

## ApiVariable

Extract values from response and save to Postman variables.

```php
use ApiDocs\Attributes\ApiVariable;

#[ApiVariable('BEARER_TOKEN', path: 'data.token')]
#[ApiVariable('USER_ID', path: 'data.user.id', scope: 'environment')]
#[ApiVariable('REFRESH_TOKEN', path: 'data.refresh_token', scope: 'global')]
public function login(LoginRequest $request): JsonResponse
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string` | required | Variable name to set |
| `path` | `string` | required | JSON path to extract value |
| `scope` | `string` | `'collection'` | Variable scope |

### Scopes

- `collection` - Collection variable (available to all requests in collection)
- `environment` - Environment variable (available based on selected environment)
- `global` - Global variable (available across all collections)

### Generated Test Script

```javascript
var jsonData = pm.response.json();
if (jsonData.data.token) {
    pm.collectionVariables.set("BEARER_TOKEN", jsonData.data.token);
    console.log("BEARER_TOKEN updated: " + jsonData.data.token);
}
```

---

## ApiHeader

Add custom headers to requests. Can be applied to class or method. Repeatable.

```php
use ApiDocs\Attributes\ApiHeader;

#[ApiHeader('X-Custom-Header', 'value')]
#[ApiHeader('X-Request-ID', '{{$guid}}')]
#[ApiHeader('X-Debug', 'true', description: 'Enable debug mode', disabled: true)]
class ApiController extends Controller
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | `string` | required | Header key |
| `value` | `string` | required | Header value (supports Postman variables) |
| `description` | `?string` | `null` | Header description |
| `disabled` | `bool` | `false` | Whether header is disabled by default |

---

## ApiQueryParam

Add query parameters to requests. Repeatable.

```php
use ApiDocs\Attributes\ApiQueryParam;

#[ApiQueryParam('page', '1')]
#[ApiQueryParam('per_page', '15', description: 'Items per page')]
#[ApiQueryParam('include', 'user,comments', description: 'Relations to include')]
#[ApiQueryParam('debug', 'true', disabled: true)]
public function index(): JsonResponse
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | `string` | required | Parameter key |
| `value` | `string` | `''` | Parameter value |
| `description` | `?string` | `null` | Parameter description |
| `disabled` | `bool` | `false` | Whether parameter is disabled by default |

### Auto-Resolution

For GET and DELETE requests without `ApiQueryParam` attributes, query parameters are automatically resolved from the FormRequest's `rules()` method. See [Auto-Resolve](auto-resolve.md#query-parameters-from-formrequest) for details.

---

## ApiAuth

Configure authentication for requests. Can be applied to class or method.

```php
use ApiDocs\Attributes\ApiAuth;

// Bearer token (default)
#[ApiAuth]
#[ApiAuth(type: 'bearer', token: '{{BEARER_TOKEN}}')]

// Basic auth
#[ApiAuth(type: 'basic', username: '{{USERNAME}}', password: '{{PASSWORD}}')]

// API key in header
#[ApiAuth(type: 'apikey', apiKey: '{{API_KEY}}', apiKeyHeader: 'X-API-Key')]

// No authentication required
#[ApiAuth(type: 'noauth')]
class PublicController extends Controller
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'bearer'` | Auth type: `bearer`, `basic`, `apikey`, `noauth` |
| `token` | `?string` | `'{{BEARER_TOKEN}}'` | Token for bearer auth |
| `username` | `?string` | `null` | Username for basic auth |
| `password` | `?string` | `null` | Password for basic auth |
| `apiKey` | `?string` | `null` | API key value |
| `apiKeyHeader` | `?string` | `'X-API-Key'` | Header name for API key |

### Auto-Detection

If no `ApiAuth` attribute is provided, the package auto-detects authentication from middleware:
- `auth:sanctum` or `auth` middleware → Bearer token auth is added

---

## ApiResponse

Define example responses manually. Repeatable.

```php
use ApiDocs\Attributes\ApiResponse;

#[ApiResponse(
    name: 'Success',
    status: 200,
    body: ['success' => true, 'data' => ['id' => 1, 'name' => 'John']]
)]
#[ApiResponse(
    name: 'Validation Error',
    status: 422,
    body: ['message' => 'The email field is required.', 'errors' => ['email' => ['Required']]]
)]
#[ApiResponse(
    name: 'Not Found',
    status: 404,
    body: ['message' => 'User not found']
)]
public function show(int $id): JsonResponse
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string` | required | Response example name |
| `status` | `int` | `200` | HTTP status code |
| `body` | `array` | `[]` | Response body |
| `headers` | `array` | `[]` | Response headers |

---

## ApiTest

Add Postman test scripts. Repeatable.

```php
use ApiDocs\Attributes\ApiTest;

#[ApiTest('pm.response.to.have.status(200);', name: 'Status is 200')]
#[ApiTest('pm.expect(pm.response.json().success).to.be.true;', name: 'Success is true')]
#[ApiTest('pm.expect(pm.response.responseTime).to.be.below(500);', name: 'Response time < 500ms')]
public function index(): JsonResponse
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `script` | `string` | required | JavaScript test script |
| `name` | `?string` | `null` | Test name (wraps in `pm.test()`) |

### Generated Output

With name:
```javascript
pm.test("Status is 200", function () {
    pm.response.to.have.status(200);
});
```

Without name (raw script):
```javascript
pm.response.to.have.status(200);
```

---

## ApiPreRequest

Add pre-request scripts. Can be applied to class or method. Repeatable.

```php
use ApiDocs\Attributes\ApiPreRequest;

// Class-level: applies to all methods in controller
#[ApiPreRequest('pm.variables.set("api_version", "v1");')]
class ApiController extends Controller
{
    // Method-level: combined with class-level scripts
    #[ApiPreRequest('pm.variables.set("timestamp", Date.now());')]
    #[ApiPreRequest('pm.variables.set("random_email", "user" + Math.random() + "@test.com");')]
    public function store(Request $request): JsonResponse
}
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `script` | `string` | required | JavaScript pre-request script |

### Script Execution Order

When both class and method-level scripts are defined:
1. Class-level scripts execute first (in order)
2. Method-level scripts execute after (in order)

---

## ApiHidden

Exclude a controller or method from the documentation.

```php
use ApiDocs\Attributes\ApiHidden;

#[ApiHidden]
class InternalController extends Controller  // Entire controller excluded

class UserController extends Controller
{
    #[ApiHidden]
    public function debug(): JsonResponse  // Only this method excluded
}
```

No parameters. Simply apply to exclude.