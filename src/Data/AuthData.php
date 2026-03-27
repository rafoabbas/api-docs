<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class AuthData
{
    public function __construct(
        public readonly string $type = 'bearer',
        public readonly ?string $token = '{{BEARER_TOKEN}}',
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $apiKey = null,
        public readonly ?string $apiKeyHeader = 'X-API-Key',
    ) {}
}
