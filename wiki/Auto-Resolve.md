# Auto-Resolve Features

The package automatically detects and resolves request/response structures from your code.

## Request Body from FormRequest

When no `ApiBody` attribute is provided, the package extracts fields from the FormRequest's `rules()` method.

### How It Works

```php
// app/Http/Requests/CreateUserRequest.php
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'age' => 'nullable|integer|min:18',
            'phone' => 'required|string',
            'avatar_url' => 'nullable|url',
            'is_active' => 'boolean',
        ];
    }
}

// app/Http/Controllers/UserController.php
class UserController extends Controller
{
    // Body auto-resolved from CreateUserRequest
    public function store(CreateUserRequest $request): JsonResponse
    {
        // ...
    }
}
```

### Generated Body

```json
{
    "name": "Example Name",
    "email": "user@example.com",
    "password": "password123",
    "age": 25,
    "phone": "+905551234567",
    "avatar_url": "https://example.com/avatar.jpg",
    "is_active": true
}
```

### Value Generation Rules

| Field Pattern | Generated Value |
|---------------|-----------------|
| `*_id`, `id` | `1` |
| `*uuid*` | `550e8400-e29b-41d4-a716-446655440000` |
| `*email*` | `user@example.com` |
| `*phone*` | `+905551234567` |
| `*name*` | `Example Name` |
| `*url*`, `*link*` | `https://example.com` |
| `*image*`, `*avatar*`, `*photo*` | `https://example.com/image.jpg` |
| `*token*` | `example_token_string` |
| `*password*` | `password123` |
| `*_at`, `*date*`, `*time*` | `2024-01-15T10:30:00Z` |
| `is_*`, `has_*`, `can_*` | `true` |
| `*status*` | `active` |
| `*type*` | `default` |
| `*count*`, `*total*`, `*amount*` | `10` |
| `*price*`, `*cost*`, `*fee*` | `99.99` |
| Fields ending in `s` (plurals) | `[]` |
| Other | `example_value` |

### Merging with ApiBody

Use `merge: true` to combine auto-resolved fields with custom values:

```php
#[ApiBody(
    data: ['status' => 'pending', 'priority' => 'high'],
    merge: true,
    except: ['created_at', 'updated_at']
)]
public function store(CreateOrderRequest $request): JsonResponse
```

Result:
1. Auto-resolve fields from `CreateOrderRequest`
2. Remove `created_at` and `updated_at`
3. Override/add `status` and `priority` from `data`

---

## Query Parameters from FormRequest

For GET and DELETE requests, the package automatically extracts query parameters from the FormRequest's `rules()` method.

### How It Works

```php
// app/Http/Requests/ListUsersRequest.php
class ListUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,pending',
            'sort_by' => 'nullable|string',
        ];
    }
}

// app/Http/Controllers/UserController.php
class UserController extends Controller
{
    // Query params auto-resolved from ListUsersRequest
    public function index(ListUsersRequest $request): JsonResponse
    {
        // ...
    }
}
```

### Generated Query Parameters

| Key | Value | Description | Disabled |
|-----|-------|-------------|----------|
| `page` | `1` | Optional | true |
| `per_page` | `10` | Optional | true |
| `search` | `search term` | Optional | true |
| `status` | `active` | Optional | true |
| `sort_by` | `value` | Optional | true |

### Value Generation Rules

| Field Pattern | Generated Value |
|---------------|-----------------|
| `page` | `1` |
| `per_page`, `limit` | `10` |
| `*_id`, `id`, `count`, `quantity` | `1` |
| `query`, `search`, `q` | `search term` |
| `*email*` | `user@example.com` |
| `*phone*` | `+905551234567` |
| `*name*` | `John Doe` |
| `*token*` | `abc123token` |
| `*uuid*` | `550e8400-e29b-41d4-a716-446655440000` |
| `*lat*`, `*latitude*` | `41.0082` |
| `*lng*`, `*lon*`, `*longitude*` | `28.9784` |
| `*price*`, `*amount*`, `*cost*` | `99.99` |
| `boolean` rule | `true` |
| `integer` rule | `1` |
| `date` rule | `2024-01-15` |
| `in:opt1,opt2` rule | First option (`opt1`) |
| Other | `value` |

### Manual Override

If you define `ApiQueryParam` attributes manually, auto-resolution is skipped:

```php
#[ApiQueryParam('page', '1', description: 'Page number')]
#[ApiQueryParam('limit', '25', description: 'Items per page')]
public function index(ListUsersRequest $request): JsonResponse
```

---

## Response from Resource

The package auto-detects return statements and resolves response structure from Resource classes.

### Detected Patterns

```php
// Direct Resource return
return new UserResource($user);
return UserResource::make($user);
return UserResource::collection($users);

// With response helper
return response()->json(['key' => 'value']);
```

### How It Works

```php
// app/Http/Resources/UserResource.php
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

// Controller
public function show(User $user): JsonResponse
{
    return new UserResource($user);  // Auto-detected
}
```

### Generated Response

```json
{
    "id": 1,
    "name": "Example Name",
    "email": "user@example.com",
    "phone": "+905551234567",
    "avatar_url": "https://example.com/image.jpg",
    "is_verified": true,
    "created_at": "2024-01-15T10:30:00Z"
}
```

### Nested Resources

Nested resources are resolved recursively:

```php
// OrderResource.php
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'customer' => CustomerResource::make($this->customer),
            'items' => OrderItemResource::collection($this->items),
        ];
    }
}
```

Generated:
```json
{
    "id": 1,
    "status": "active",
    "customer": {
        "id": 1,
        "name": "Example Name",
        "email": "user@example.com"
    },
    "items": []
}
```

### Conditional Fields

`$this->when()` conditions are also analyzed:

```php
return [
    'id' => $this->id,
    'secret' => $this->when($this->isAdmin(), $this->secret),
    'profile' => $this->when($this->profile, ProfileResource::make($this->profile)),
];
```

### Using ApiResource Attribute

For explicit control:

```php
#[ApiResource(UserResource::class)]
public function show(User $user): JsonResponse

#[ApiResource(UserResource::class, wrapped: true, status: 200)]
public function profile(): JsonResponse
```

### Using Resource in YAML

Resource resolution also works in YAML definitions via the `resource` field:

```yaml
requests:
  - name: Get User
    method: GET
    uri: /v1/users/{id}
    resource: App\Http\Resources\UserResource
    resource_status: 200
    resource_wrapped: true

  - name: List Users
    method: GET
    uri: /v1/users
    resource: App\Http\Resources\UserResource
    resource_collection: true
```

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `resource` | `string` | `null` | Fully qualified Resource class name |
| `resource_status` | `int` | `200` | HTTP status code for the response |
| `resource_wrapped` | `bool` | auto-detected | Wrap in standard API response format |
| `resource_collection` | `bool` | auto-detected | Return as array/collection |

---

## Authentication Auto-Detection

The package detects authentication requirements from middleware.

### Detected Middleware

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

Both `auth:sanctum` and `auth` middleware trigger automatic Bearer token authentication in Postman.

### Override with ApiAuth

```php
#[ApiAuth(type: 'noauth')]  // Explicitly no auth
public function publicEndpoint(): JsonResponse

#[ApiAuth(type: 'apikey', apiKey: '{{API_KEY}}')]  // Different auth type
public function webhookEndpoint(): JsonResponse
```

---

## Route Parameter Detection

URI parameters are automatically converted to Postman path variables.

### Laravel Route

```php
Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/orders/{order}/items/{item}', [OrderController::class, 'showItem']);
Route::get('/posts/{post?}', [PostController::class, 'show']);  // Optional
```

### Generated Postman URL

```
{{API_URL}}/users/:user
{{API_URL}}/orders/:order/items/:item
{{API_URL}}/posts/:post
```

Path variables appear in Postman's URL params section for easy editing.

---

## Namespace Resolution

The package resolves Resource class namespaces in this order:

1. **Use statements** - Imports at top of controller file
2. **Same namespace** - Same directory as controller
3. **Common namespaces**:
   - `App\Http\Resources`
   - `App\Http\Resources\Api`
   - `App\Http\Resources\Api\V1`
   - `App\Http\Resources\Api\V1\Customer`

```php
// If controller has:
use App\Http\Resources\V2\UserResource;

// This will resolve correctly:
return UserResource::make($user);
```

---

## Limitations

1. **Complex logic** - Only simple `return` statements are detected
2. **Dynamic returns** - Conditional returns based on runtime values aren't detected
3. **Abstract resources** - Base resource classes aren't analyzed
4. **Closures** - Inline closures in routes aren't supported

For complex cases, use explicit attributes:

```php
#[ApiBody(['complex' => 'structure'])]
#[ApiResource(CustomResource::class)]
#[ApiResponse(name: 'Success', status: 200, body: ['custom' => 'response'])]
public function complexEndpoint(): JsonResponse
```