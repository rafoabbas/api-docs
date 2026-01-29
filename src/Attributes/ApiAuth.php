<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiAuth
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
        public string $type = self::TYPE_BEARER,
        public ?string $token = '{{BEARER_TOKEN}}',
        public ?string $username = null,
        public ?string $password = null,
        public ?string $apiKey = null,
        public ?string $apiKeyHeader = 'X-API-Key',
    ) {}
}
