<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class AuthData
{
    public function __construct(
        public string $type = 'bearer',
        public ?string $token = '{{BEARER_TOKEN}}',
        public ?string $username = null,
        public ?string $password = null,
        public ?string $apiKey = null,
        public ?string $apiKeyHeader = 'X-API-Key',
    ) {}
}
