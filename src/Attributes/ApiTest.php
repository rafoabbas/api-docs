<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiTest
{
    /**
     * @param  string  $script  JavaScript test script
     * @param  string|null  $name  Test name (for pm.test wrapper)
     */
    public function __construct(
        public readonly string $script,
        public readonly ?string $name = null,
    ) {}
}
