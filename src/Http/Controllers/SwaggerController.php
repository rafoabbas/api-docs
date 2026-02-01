<?php

declare(strict_types=1);

namespace ApiDocs\Http\Controllers;

use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Collectors\RequestMerger;
use ApiDocs\Collectors\YamlCollector;
use ApiDocs\Generators\OpenApiGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SwaggerController extends Controller
{
    public function __construct(
        private readonly AttributeCollector $attributeCollector,
        private readonly YamlCollector $yamlCollector,
    ) {}

    public function index(Request $request): Response
    {
        $this->validateToken($request);

        $config = config('api-docs.swagger', []);
        $title = config('api-docs.openapi.title', 'API Documentation');
        $darkMode = $config['dark_mode'] ?? true;
        $persistAuthorization = $config['persist_authorization'] ?? true;
        $token = $request->query('token');

        $specUrl = url(config('api-docs.swagger.path', '/api/docs') . '/openapi.json');

        if ($token) {
            $specUrl .= '?token=' . $token;
        }

        return response()->view('api-docs::swagger', [
            'title' => $title,
            'darkMode' => $darkMode,
            'persistAuthorization' => $persistAuthorization,
            'specUrl' => $specUrl,
            'defaultHeaders' => config('api-docs.default_headers', []),
        ]);
    }

    private function validateToken(Request $request): void
    {
        $configToken = config('api-docs.swagger.token');

        if ($configToken === null || $configToken === '') {
            return;
        }

        $headerName = config('api-docs.swagger.token_header', 'X-Api-Docs-Token');
        $providedToken = $request->query('token') ?? $request->header($headerName);

        if ($providedToken !== $configToken) {
            throw new AccessDeniedHttpException('Invalid or missing access token.');
        }
    }

    public function openapi(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $requests = $this->attributeCollector->collect();
        $yamlRequests = $this->yamlCollector->collect();

        $merger = new RequestMerger;
        $requests = $merger->merge($requests, $yamlRequests);

        $generator = new OpenApiGenerator;

        $openApiConfig = config('api-docs.openapi', []);

        $generator->setTitle($openApiConfig['title'] ?? config('app.name', 'API Documentation'));
        $generator->setVersion($openApiConfig['version'] ?? '1.0.0');

        if (! empty($openApiConfig['description'])) {
            $generator->setDescription($openApiConfig['description']);
        }

        if (! empty($openApiConfig['servers'])) {
            $generator->setServers($openApiConfig['servers']);
        } else {
            $generator->setDefaultServer(url('/'));
        }

        $spec = $generator->generate($requests);

        return response()->json($spec)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
        ]);
    }
}
