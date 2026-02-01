<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    @if($darkMode)
    <style>
        body {
            background-color: #1a1a1a;
        }
        .swagger-ui {
            filter: invert(88%) hue-rotate(180deg);
        }
        .swagger-ui .microlight {
            filter: invert(100%) hue-rotate(180deg);
        }
        .swagger-ui svg {
            filter: invert(100%) hue-rotate(180deg);
        }
        .swagger-ui img {
            filter: invert(100%) hue-rotate(180deg);
        }
    </style>
    @endif
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: "{{ $specUrl }}",
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout",
                deepLinking: true,
                persistAuthorization: {{ $persistAuthorization ? 'true' : 'false' }},
                displayRequestDuration: true,
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                tryItOutEnabled: true,
                requestInterceptor: (request) => {
                    const defaultHeaders = @json($defaultHeaders);
                    // Replace all headers with config headers only
                    const newHeaders = {};
                    // Keep Authorization if exists
                    if (request.headers['Authorization']) {
                        newHeaders['Authorization'] = request.headers['Authorization'];
                    }
                    // Apply config headers
                    Object.keys(defaultHeaders).forEach(key => {
                        newHeaders[key] = defaultHeaders[key];
                    });
                    request.headers = newHeaders;
                    return request;
                }
            });
        };
    </script>
</body>
</html>
