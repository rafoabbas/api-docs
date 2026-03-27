<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiQueryParam
{
    /**
     * @param  string  $key  Query parameter key
     * @param  string  $value  Query parameter value (supports variables: {{VAR}})
     * @param  string|null  $description  Parameter description
     * @param  bool  $disabled  Whether parameter is disabled by default
     */
    public function __construct(
        public readonly string $key,
        public readonly string $value = '',
        public readonly ?string $description = null,
        public readonly bool $disabled = false,
    ) {}
}
