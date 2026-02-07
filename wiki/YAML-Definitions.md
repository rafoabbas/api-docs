# YAML Definitions

Define API endpoints in YAML files as an alternative or supplement to PHP attributes. YAML definitions take priority over PHP attributes when both exist for the same endpoint.

## Setup

1. Create directory: `resources/api-docs/`
2. Add YAML files (`.yaml` or `.yml`)
3. Run `php artisan api:generate`

## File Structure

```yaml
# resources/api-docs/auth.yaml
folder: V1 / Customer / Auth

requests:
  - name: Login
    method: POST
    uri: /v1/auth/login
    description: Authenticate user and get access token
    body:
      phone: "905551234567"
      password: "secret123"
    variables:
      - name: BEARER_TOKEN
        path: data.token
    responses:
      - name: Success
        status: 200
        body:
          success: true
          data:
            token: "eyJhbGciOiJIUzI1NiIs..."
            user:
              id: 1
              phone: "905551234567"

  - name: Logout
    method: POST
    uri: /v1/auth/logout
    auth:
      type: bearer
```

## Full Schema

```yaml
# Folder name for all requests in this file
folder: string  # Required. Supports nesting: "V1 / Auth / OTP"

# ─── File-level shared settings ───────────────────────────────────
# These apply to ALL requests in this file (can be overridden per request)

# Shared authentication (applied to all requests unless overridden)
auth:
  type: string             # 'bearer', 'basic', 'apikey', 'noauth'
  token: string            # For bearer: '{{BEARER_TOKEN}}'

# Shared headers (merged with request-level headers)
headers:
  X-Custom-Header: value

# Shared pre-request scripts (prepended to request-level scripts)
pre_request_scripts:
  - string                 # JavaScript code

# ─── Requests ─────────────────────────────────────────────────────

requests:
  - name: string           # Required. Request name
    method: string         # Required. HTTP method: GET, POST, PUT, PATCH, DELETE
    uri: string            # Required. Request URI: /v1/users/{id}

    # Optional fields
    description: string    # Request description
    folder: string         # Override file-level folder for this request
    order: int             # Order within folder (default: 0)
    hidden: bool           # Exclude this request from docs (default: false)

    # Request body
    body:                  # Object with key-value pairs
      key: value
    body_mode: string      # 'raw' (default), 'formdata', 'urlencoded'
    body_language: string  # 'json' (default), 'xml', 'text'
    body_merge: bool       # Merge YAML body with auto-resolved FormRequest body (default: false)
    body_except:           # Keys to exclude when body_merge is true
      - field_name

    # Response Resource (auto-resolve response structure)
    resource: string             # Fully qualified Resource class: App\Http\Resources\UserResource
    resource_status: int         # HTTP status code (default: 200)
    resource_wrapped: bool       # Wrap in standard response format (auto-detected if omitted)
    resource_collection: bool    # Return as array/collection (auto-detected if omitted)

    # Headers
    headers:
      # Simple format
      X-Custom-Header: value

      # Detailed format
      - key: X-Custom-Header
        value: value
        description: Header description
        disabled: false

    # Query parameters
    query_params:
      # Simple format
      page: "1"

      # Detailed format
      - key: page
        value: "1"
        description: Page number
        disabled: false

    # Authentication
    auth:
      type: string         # 'bearer', 'basic', 'apikey', 'noauth'
      token: string        # For bearer: '{{BEARER_TOKEN}}'
      username: string     # For basic auth
      password: string     # For basic auth
      api_key: string      # For apikey
      api_key_header: string  # For apikey: 'X-API-Key'

    # Variable extraction from response
    variables:
      - name: string       # Variable name: BEARER_TOKEN
        path: string       # JSON path: data.token
        scope: string      # 'collection', 'environment', 'global'

    # Example responses
    responses:
      - name: string       # Response name: 'Success'
        status: int        # HTTP status: 200
        body:              # Response body object
          key: value
        headers:           # Response headers
          Content-Type: application/json

    # Test scripts
    tests:
      - script: string     # JavaScript: 'pm.response.to.have.status(200);'
        name: string       # Optional test name

    # Pre-request scripts
    pre_request_scripts:
      - string             # JavaScript code

    # Middleware (for auto-auth detection)
    middleware:
      - auth:sanctum
```

## Examples

### Basic CRUD

```yaml
# resources/api-docs/users.yaml
folder: V1 / Users

requests:
  - name: List Users
    method: GET
    uri: /v1/users
    query_params:
      page: "1"
      per_page: "15"
    responses:
      - name: Success
        status: 200
        body:
          success: true
          data:
            - id: 1
              name: John Doe
              email: john@example.com

  - name: Create User
    method: POST
    uri: /v1/users
    body:
      name: John Doe
      email: john@example.com
      password: secret123
    responses:
      - name: Created
        status: 201
        body:
          success: true
          data:
            id: 1
            name: John Doe

  - name: Get User
    method: GET
    uri: /v1/users/{id}

  - name: Update User
    method: PUT
    uri: /v1/users/{id}
    body:
      name: John Updated

  - name: Delete User
    method: DELETE
    uri: /v1/users/{id}
```

### Authentication Flow

```yaml
# resources/api-docs/auth.yaml
folder: V1 / Auth

requests:
  - name: Request OTP
    method: POST
    uri: /v1/auth/otp/request
    order: 1
    auth:
      type: noauth
    body:
      phone: "905551234567"
    responses:
      - name: Success
        status: 200
        body:
          success: true
          message: OTP sent successfully

  - name: Verify OTP
    method: POST
    uri: /v1/auth/otp/verify
    order: 2
    auth:
      type: noauth
    body:
      phone: "905551234567"
      code: "123456"
    variables:
      - name: BEARER_TOKEN
        path: data.token
    responses:
      - name: Success
        status: 200
        body:
          success: true
          data:
            token: "eyJhbGciOiJIUzI1NiIs..."

  - name: Get Profile
    method: GET
    uri: /v1/auth/me
    order: 3
    auth:
      type: bearer
    responses:
      - name: Success
        status: 200
        body:
          success: true
          data:
            id: 1
            phone: "905551234567"
```

### File Upload

```yaml
# resources/api-docs/uploads.yaml
folder: V1 / Uploads

requests:
  - name: Upload Avatar
    method: POST
    uri: /v1/users/avatar
    body_mode: formdata
    body:
      avatar: "@/path/to/image.jpg"
    headers:
      Content-Type: multipart/form-data
```

### With Tests

```yaml
folder: V1 / Orders

requests:
  - name: Create Order
    method: POST
    uri: /v1/orders
    body:
      product_id: 1
      quantity: 2
    variables:
      - name: ORDER_ID
        path: data.id
    tests:
      - script: "pm.response.to.have.status(201);"
        name: Status is 201
      - script: "pm.expect(pm.response.json().data.id).to.be.a('number');"
        name: Has order ID
    pre_request_scripts:
      - "pm.variables.set('timestamp', Date.now());"
```

### Resource Auto-Resolve

Use `resource` to auto-generate response structure from a Resource class:

```yaml
folder: V1 / Users

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

### Body Merge with FormRequest

Use `body_merge` to combine YAML body with auto-resolved FormRequest fields:

```yaml
folder: V1 / Orders

requests:
  - name: Calculate Order
    method: POST
    uri: /v1/orders/calculate
    body:
      service_type: towing
      location_lat: 41.0082
      location_lng: 28.9784
    body_merge: true
    body_except:
      - uuid
      - user_id
      - created_at
```

When `body_merge: true`:
1. Fields are auto-resolved from the route's FormRequest `rules()`
2. Fields listed in `body_except` are removed
3. YAML `body` values are merged in (YAML values override resolved values)

### Hidden Requests

Use `hidden` to exclude a request from documentation:

```yaml
folder: V1 / Internal

requests:
  - name: Health Check
    method: GET
    uri: /v1/health
    hidden: true
```

### File-Level Shared Settings

Define `auth`, `headers`, and `pre_request_scripts` at the file level to apply them to all requests:

```yaml
folder: V1 / Customer / Profile

auth:
  type: bearer

headers:
  X-App-Version: "1.0.0"

pre_request_scripts:
  - "pm.variables.set('timestamp', Date.now());"

requests:
  - name: Get Profile
    method: GET
    uri: /v1/profile
    # Inherits file-level auth, headers, and pre_request_scripts

  - name: Update Profile
    method: PUT
    uri: /v1/profile
    body:
      name: "John Doe"
    # Also inherits file-level settings

  - name: Public Endpoint
    method: GET
    uri: /v1/profile/public
    auth:
      type: noauth
    # Overrides file-level auth for this request only
```

File-level shared settings behavior:
- **auth**: Applied to requests that don't define their own `auth`
- **headers**: Merged with request-level headers (request headers take priority)
- **pre_request_scripts**: Prepended to request-level scripts

## Merging with Attributes

YAML and PHP attributes are merged together:

1. Both sources are collected
2. Requests are matched by `method + uri`
3. **YAML definitions take priority** over PHP attributes
4. Merge is done field-by-field (non-null YAML fields override attribute fields)
5. Unmatched requests from both sources are included

This allows you to:
- Use a **YAML-first workflow** where controllers stay clean
- Define all API documentation in YAML files
- Let auto-resolve features (FormRequest, Resource, middleware) fill in the gaps
- Use `body_merge` to combine YAML body with auto-resolved FormRequest body
- Use `resource` to auto-resolve response structure without PHP attributes

## Custom YAML Path

```php
// config/api-docs.php
'yaml_path' => resource_path('api-docs'),
```

Or via command:
```bash
php artisan api:generate --yaml=resources/custom-api-docs
```

## File Organization

Recommended structure:
```
resources/api-docs/
├── auth.yaml           # Authentication endpoints
├── users.yaml          # User management
├── orders.yaml         # Order endpoints
├── v2/
│   ├── auth.yaml       # V2 auth endpoints
│   └── users.yaml      # V2 user endpoints
└── external/
    └── payment.yaml    # Third-party payment API
```

Files are discovered recursively and sorted alphabetically.