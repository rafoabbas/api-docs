<?php

declare(strict_types=1);

use ApiDocs\Attributes\ApiAuth;
use ApiDocs\Attributes\ApiDeprecated;
use ApiDocs\Attributes\ApiFolder;
use ApiDocs\Attributes\ApiHeader;
use ApiDocs\Attributes\ApiHidden;
use ApiDocs\Attributes\ApiRequest;
use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Data\RequestData;
use ApiDocs\Tests\TestCase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

uses(TestCase::class);

// ---------------------------------------------------------------------------
// Fixture controllers used across tests
// ---------------------------------------------------------------------------

#[ApiFolder('Users')]
class AttributeCollectorTestController
{
    #[ApiRequest(name: 'List Users', description: 'Get all users')]
    public function index(): void {}

    #[ApiRequest(name: 'Show User', description: 'Get a single user')]
    public function show(): void {}

    #[ApiHidden]
    public function hidden(): void {}
}

class AttributeCollectorNoFolderController
{
    #[ApiRequest(name: 'List Posts')]
    public function index(): void {}

    public function show(): void {}
}

#[ApiHidden]
class AttributeCollectorHiddenClassController
{
    #[ApiRequest(name: 'Should Not Appear')]
    public function index(): void {}

    public function show(): void {}
}

#[ApiFolder('Class Level Folder')]
class AttributeCollectorMethodFolderController
{
    #[ApiFolder('Method Level Folder')]
    #[ApiRequest(name: 'Method Folder Request')]
    public function methodFolder(): void {}

    #[ApiRequest(name: 'Class Folder Request')]
    public function classFolder(): void {}
}

#[ApiFolder('Deprecated')]
class AttributeCollectorDeprecatedController
{
    #[ApiDeprecated(reason: 'Use v2', replacement: '/api/v2/items')]
    #[ApiRequest(name: 'Old Endpoint')]
    public function deprecated(): void {}
}

#[ApiFolder('Auth')]
class AttributeCollectorAuthController
{
    #[ApiAuth(type: 'bearer', token: '{{TOKEN}}')]
    #[ApiRequest(name: 'Authenticated')]
    public function authed(): void {}
}

#[ApiFolder('Headers')]
#[ApiHeader(key: 'X-Class-Header', value: 'class-value')]
class AttributeCollectorHeaderController
{
    #[ApiHeader(key: 'X-Method-Header', value: 'method-value')]
    #[ApiRequest(name: 'With Headers')]
    public function withHeaders(): void {}
}

class AttributeCollectorExcludedController
{
    #[ApiRequest(name: 'Excluded Route')]
    public function index(): void {}
}

class AttributeCollectorNamedRouteController
{
    public function index(): void {}

    public function show(): void {}

    public function listItems(): void {}
}

// ---------------------------------------------------------------------------
// Helper: create a collector and filter results to only test-registered URIs
// ---------------------------------------------------------------------------

/**
 * Register routes, collect all results, then filter to only the URIs
 * that were explicitly registered via the given callback. This isolates
 * tests from package-registered routes (e.g. swagger routes).
 *
 * @return array<int, RequestData>
 */
function collectForRoutes(Closure $routeRegistrar, array $testUris): array
{
    $routeRegistrar();

    /** @var Router $router */
    $router = app(Router::class);

    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    // Normalize URIs (Laravel strips leading slashes)
    $normalized = array_map(fn (string $uri) => ltrim($uri, '/'), $testUris);

    return array_values(array_filter(
        $all,
        fn (RequestData $r) => in_array($r->uri, $normalized, true),
    ));
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('collects routes with ApiRequest attributes', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/users', [AttributeCollectorTestController::class, 'index']);
            Route::get('/api/users/{id}', [AttributeCollectorTestController::class, 'show']);
        },
        ['api/users', 'api/users/{id}'],
    );

    expect($requests)->toHaveCount(2);
    expect($requests[0])->toBeInstanceOf(RequestData::class);
    expect($requests[0]->name)->toBe('List Users');
    expect($requests[0]->description)->toBe('Get all users');
    expect($requests[0]->method)->toBe('GET');
    expect($requests[0]->uri)->toBe('api/users');
    expect($requests[0]->folder)->toBe('Users');

    expect($requests[1]->name)->toBe('Show User');
    expect($requests[1]->description)->toBe('Get a single user');
    expect($requests[1]->uri)->toBe('api/users/{id}');
});

it('excludes routes with default prefixes', function (): void {
    // The config in TestCase sets exclude_prefixes to ['_', 'sanctum', 'telescope']
    Route::get('/api/test-users', [AttributeCollectorTestController::class, 'index']);
    Route::get('_ignition/health-check', [AttributeCollectorExcludedController::class, 'index']);
    Route::get('sanctum/csrf-cookie', [AttributeCollectorExcludedController::class, 'index']);
    Route::get('telescope/requests', [AttributeCollectorExcludedController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/test-users');
    expect($uris)->not->toContain('_ignition/health-check');
    expect($uris)->not->toContain('sanctum/csrf-cookie');
    expect($uris)->not->toContain('telescope/requests');
});

it('applies custom exclude prefixes via setExcludePrefixes', function (): void {
    Route::get('/api/test-users-custom', [AttributeCollectorTestController::class, 'index']);
    Route::get('/admin/dashboard', [AttributeCollectorNoFolderController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $collector->setExcludePrefixes(['admin']);

    // Override config so collect() uses our custom prefixes from setExcludePrefixes
    config(['api-docs.exclude_prefixes' => ['admin']]);

    $all = $collector->collect();
    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/test-users-custom');
    expect($uris)->not->toContain('admin/dashboard');
});

it('skips HEAD methods', function (): void {
    // Route::get registers both GET and HEAD
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/test-head', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/test-head'],
    );

    $methods = array_map(fn (RequestData $r) => $r->method, $requests);

    expect($methods)->toContain('GET');
    expect($methods)->not->toContain('HEAD');
    expect($requests)->toHaveCount(1);
});

it('skips closure routes without controllers', function (): void {
    Route::get('/api/closure-test', fn () => 'hello');
    Route::get('/api/controller-test', [AttributeCollectorTestController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/controller-test');
    expect($uris)->not->toContain('api/closure-test');
});

it('skips routes with ApiHidden on method', function (): void {
    Route::get('/api/visible-users', [AttributeCollectorTestController::class, 'index']);
    Route::get('/api/hidden-endpoint', [AttributeCollectorTestController::class, 'hidden']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/visible-users');
    expect($uris)->not->toContain('api/hidden-endpoint');
});

it('skips all routes for a class with ApiHidden', function (): void {
    Route::get('/api/visible', [AttributeCollectorTestController::class, 'index']);
    Route::get('/api/hidden-class', [AttributeCollectorHiddenClassController::class, 'index']);
    Route::get('/api/hidden-class/detail', [AttributeCollectorHiddenClassController::class, 'show']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/visible');
    expect($uris)->not->toContain('api/hidden-class');
    expect($uris)->not->toContain('api/hidden-class/detail');
});

it('generates request name from route name when no ApiRequest name', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/named-items', [AttributeCollectorNamedRouteController::class, 'index'])
                ->name('items.index');
        },
        ['api/named-items'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->name)->toBe('Index');
});

it('generates request name from URI when no route name and no ApiRequest', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/products', [AttributeCollectorNamedRouteController::class, 'index']);
        },
        ['api/products'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->name)->toBe('GET Products');
});

it('generates name with by ID suffix for parameterized URI segments', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/products/{id}', [AttributeCollectorNamedRouteController::class, 'show']);
        },
        ['api/products/{id}'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->name)->toBe('GET Products by ID');
});

it('generates request name with hyphens and underscores replaced', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/list-items', [AttributeCollectorNamedRouteController::class, 'listItems'])
                ->name('items.list-items');
        },
        ['api/list-items'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->name)->toBe('List Items');
});

it('determines folder from URI when no ApiFolder attribute', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/posts', [AttributeCollectorNoFolderController::class, 'index']);
        },
        ['api/posts'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->folder)->toBe('Api / Posts');
});

it('determines folder from URI with v1 prefix', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/v1/api/posts', [AttributeCollectorNoFolderController::class, 'index']);
        },
        ['v1/api/posts'],
    );

    expect($requests)->toHaveCount(1);
    // v1 prefix: uses parts[1] / parts[2]
    expect($requests[0]->folder)->toBe('Api / Posts');
});

it('uses ApiFolder from class level', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/class-folder-users', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/class-folder-users'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->folder)->toBe('Users');
});

it('uses ApiFolder from method level overriding class level', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/method-folder', [AttributeCollectorMethodFolderController::class, 'methodFolder']);
            Route::get('/api/class-folder', [AttributeCollectorMethodFolderController::class, 'classFolder']);
        },
        ['api/method-folder', 'api/class-folder'],
    );

    expect($requests)->toHaveCount(2);

    $methodFolderRequest = collect($requests)->firstWhere('name', 'Method Folder Request');
    $classFolderRequest = collect($requests)->firstWhere('name', 'Class Folder Request');

    expect($methodFolderRequest)->not->toBeNull();
    expect($classFolderRequest)->not->toBeNull();
    expect($methodFolderRequest->folder)->toBe('Method Level Folder');
    expect($classFolderRequest->folder)->toBe('Class Level Folder');
});

it('excludes the up health check route', function (): void {
    Route::get('/api/test-up-users', [AttributeCollectorTestController::class, 'index']);
    Route::get('up', [AttributeCollectorExcludedController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/test-up-users');
    expect($uris)->not->toContain('up');
});

it('collects multiple HTTP methods for same URI', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::match(['GET', 'POST'], '/api/multi-method-items', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/multi-method-items'],
    );

    // GET and POST (HEAD is skipped)
    expect($requests)->toHaveCount(2);

    $methods = array_map(fn (RequestData $r) => $r->method, $requests);

    expect($methods)->toContain('GET');
    expect($methods)->toContain('POST');
    expect($methods)->not->toContain('HEAD');
});

it('collects deprecated attribute data', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/old-items', [AttributeCollectorDeprecatedController::class, 'deprecated']);
        },
        ['api/old-items'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->deprecated)->toBeTrue();
    expect($requests[0]->deprecatedReason)->toBe('Use v2');
    expect($requests[0]->deprecatedReplacement)->toBe('/api/v2/items');
});

it('collects auth attribute data', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/secure-endpoint', [AttributeCollectorAuthController::class, 'authed']);
        },
        ['api/secure-endpoint'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->auth)->not->toBeNull();
    expect($requests[0]->auth->type)->toBe('bearer');
    expect($requests[0]->auth->token)->toBe('{{TOKEN}}');
});

it('collects headers from class and method level', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/headers-test', [AttributeCollectorHeaderController::class, 'withHeaders']);
        },
        ['api/headers-test'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->headers)->toHaveCount(2);

    $headerKeys = array_map(fn ($h) => $h->key, $requests[0]->headers);

    expect($headerKeys)->toContain('X-Class-Header');
    expect($headerKeys)->toContain('X-Method-Header');
});

it('collects middleware from route', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/middleware-test', [AttributeCollectorTestController::class, 'index'])
                ->middleware(['auth:sanctum', 'throttle:60,1']);
        },
        ['api/middleware-test'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->middleware)->toContain('auth:sanctum');
    expect($requests[0]->middleware)->toContain('throttle:60,1');
});

it('returns no custom routes when none are registered', function (): void {
    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    // Filter to only look for routes using our test fixture controllers
    $fixtureControllerRoutes = array_filter(
        $all,
        fn (RequestData $r) => str_starts_with($r->uri, 'api/test-'),
    );

    expect($fixtureControllerRoutes)->toBeEmpty();
});

it('handles single-segment URI for folder determination', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/dashboard', [AttributeCollectorNoFolderController::class, 'index']);
        },
        ['dashboard'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->folder)->toBe('Dashboard');
});

it('preserves ApiRequest order property', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/order-test', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/order-test'],
    );

    expect($requests)->toHaveCount(1);
    // Default order is 0 when not specified
    expect($requests[0]->order)->toBe(0);
});

it('excludes storage prefix by default', function (): void {
    // Note: The TestCase config sets exclude_prefixes to ['_', 'sanctum', 'telescope'],
    // which does NOT include 'storage'. We override config to include it for this test.
    config(['api-docs.exclude_prefixes' => ['_', 'sanctum', 'telescope', 'storage']]);

    Route::get('/api/storage-test-users', [AttributeCollectorTestController::class, 'index']);
    Route::get('storage/files', [AttributeCollectorExcludedController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/storage-test-users');
    expect($uris)->not->toContain('storage/files');
});

it('excludes mcp prefix by default', function (): void {
    // Note: The TestCase config sets exclude_prefixes to ['_', 'sanctum', 'telescope'],
    // which does NOT include 'mcp'. We override config to include it for this test.
    config(['api-docs.exclude_prefixes' => ['_', 'sanctum', 'telescope', 'mcp']]);

    Route::get('/api/mcp-test-users', [AttributeCollectorTestController::class, 'index']);
    Route::get('mcp/something', [AttributeCollectorExcludedController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->toContain('api/mcp-test-users');
    expect($uris)->not->toContain('mcp/something');
});

it('collects POST route with correct method', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::post('/api/post-test', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/post-test'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->method)->toBe('POST');
});

it('collects PUT route with correct method', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::put('/api/put-test/{id}', [AttributeCollectorTestController::class, 'show']);
        },
        ['api/put-test/{id}'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->method)->toBe('PUT');
});

it('collects DELETE route with correct method', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::delete('/api/delete-test/{id}', [AttributeCollectorTestController::class, 'show']);
        },
        ['api/delete-test/{id}'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->method)->toBe('DELETE');
});

it('collects PATCH route with correct method', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::patch('/api/patch-test/{id}', [AttributeCollectorTestController::class, 'show']);
        },
        ['api/patch-test/{id}'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->method)->toBe('PATCH');
});

it('uses exclude_prefixes from config', function (): void {
    config(['api-docs.exclude_prefixes' => ['api/v1']]);

    Route::get('/api/v1/config-users', [AttributeCollectorTestController::class, 'index']);
    Route::get('/api/v2/config-users', [AttributeCollectorNoFolderController::class, 'index']);

    /** @var Router $router */
    $router = app(Router::class);
    $collector = new AttributeCollector($router);
    $all = $collector->collect();

    $uris = array_map(fn (RequestData $r) => $r->uri, $all);

    expect($uris)->not->toContain('api/v1/config-users');
    expect($uris)->toContain('api/v2/config-users');
});

it('sets non-deprecated flag when no ApiDeprecated attribute', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/not-deprecated', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/not-deprecated'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->deprecated)->toBeFalse();
    expect($requests[0]->deprecatedReason)->toBeNull();
    expect($requests[0]->deprecatedReplacement)->toBeNull();
});

it('sets auth to null when no ApiAuth attribute', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/no-auth', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/no-auth'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->auth)->toBeNull();
});

it('sets body to null for GET requests', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/get-nobody', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/get-nobody'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->body)->toBeNull();
});

it('sets default body mode and language', function (): void {
    $requests = collectForRoutes(
        function (): void {
            Route::get('/api/body-defaults', [AttributeCollectorTestController::class, 'index']);
        },
        ['api/body-defaults'],
    );

    expect($requests)->toHaveCount(1);
    expect($requests[0]->bodyMode)->toBe('raw');
    expect($requests[0]->bodyLanguage)->toBe('json');
});
