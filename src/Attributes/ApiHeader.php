<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiHeader
{
    /**
     * @param  string  $key  Header key
     * @param  string  $value  Header value (supports variables: {{VAR}})
     * @param  string|null  $description  Header description
     * @param  bool  $disabled  Whether header is disabled by default
     */
    public function __construct(
        public string $key,
        public string $value,
        public ?string $description = null,
        public bool $disabled = false,
    ) {}
}
