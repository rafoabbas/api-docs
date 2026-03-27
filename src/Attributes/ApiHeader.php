<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiHeader
{
    /**
     * @param  string  $key  Header key
     * @param  string  $value  Header value (supports variables: {{VAR}})
     * @param  string|null  $description  Header description
     * @param  bool  $disabled  Whether header is disabled by default
     */
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly ?string $description = null,
        public readonly bool $disabled = false,
    ) {}
}
