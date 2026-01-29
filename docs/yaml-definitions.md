# YAML Definitions

Define API endpoints in YAML files as an alternative or supplement to PHP attributes.

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

# List of requests
requests:
  - name: string           # Required. Request name
    method: string         # Required. HTTP method: GET, POST, PUT, PATCH, DELETE
    uri: string            # Required. Request URI: /v1/users/{id}

    # Optional fields
    description: string    # Request description
    folder: string         # Override file-level folder for this request
    order: int             # Order within folder (default: 0)

    # Request body
    body:                  # Object with key-value pairs
      key: value
    body_mode: string      # 'raw' (default), 'formdata', 'urlencoded'
    body_language: string  # 'json' (default), 'xml', 'text'

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

## Merging with Attributes

YAML and PHP attributes are merged together:

1. Both sources are collected
2. Requests are matched by `method + uri`
3. **PHP attributes take priority** over YAML
4. Unmatched requests from both sources are included

This allows you to:
- Define base structure in YAML
- Override specific details with attributes
- Use YAML for external/third-party API documentation
- Use attributes for your Laravel routes

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