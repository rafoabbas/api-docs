<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ApiAuth
{
    public const TYPE_BEARER = 'bearer';

    public const TYPE_BASIC = 'basic';

    public const TYPE_API_KEY = 'apikey';

    public const TYPE_NONE = 'noauth';

    /**
     * @param  string  $type  Auth type: bearer, basic, apikey, noauth
     * @param  string|null  $token  Token variable name (default: BEARER_TOKEN)
     * @param  string|null  $username  Username for basic auth
     * @param  string|null  $password  Password for basic auth
     * @param  string|null  $apiKey  API key value
     * @param  string|null  $apiKeyHeader  API key header name
     */
    public function __construct(
        public readonly string $type = self::TYPE_BEARER,
        public readonly ?string $token = '{{BEARER_TOKEN}}',
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $apiKey = null,
        public readonly ?string $apiKeyHeader = 'X-API-Key',
    ) {}
}
