<?php

declare(strict_types=1);

namespace ApiDocs\Http\Controllers;

use ApiDocs\Collectors\AttributeCollector;
use ApiDocs\Collectors\RequestMerger;
use ApiDocs\Collectors\YamlCollector;
use ApiDocs\Generators\OpenApiGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SwaggerController extends Controller
{
    public function __construct(
        private readonly AttributeCollector $attributeCollector,
        private readonly YamlCollector $yamlCollector,
    ) {}

    public function index(): Response
    {
        $config = config('api-docs.swagger', []);
        $title = config('api-docs.openapi.title', 'API Documentation');
        $darkMode = $config['dark_mode'] ?? true;
        $persistAuthorization = $config['persist_authorization'] ?? true;

        return response()->view('api-docs::swagger', [
            'title' => $title,
            'darkMode' => $darkMode,
            'persistAuthorization' => $persistAuthorization,
            'specUrl' => url(config('api-docs.swagger.path', '/api/docs') . '/openapi.json'),
        ]);
    }

    public function openapi(): JsonResponse
    {
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
