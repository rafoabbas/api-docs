<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiPreRequest
{
    /**
     * @param  string  $script  JavaScript pre-request script
     */
    public function __construct(
        public string $script,
    ) {}
}
