<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiQueryParam
{
    /**
     * @param  string  $key  Query parameter key
     * @param  string  $value  Query parameter value (supports variables: {{VAR}})
     * @param  string|null  $description  Parameter description
     * @param  bool  $disabled  Whether parameter is disabled by default
     */
    public function __construct(
        public string $key,
        public string $value = '',
        public ?string $description = null,
        public bool $disabled = false,
    ) {}
}
