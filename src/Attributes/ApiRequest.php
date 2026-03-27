<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class ApiRequest
{
    /**
     * @param  string|null  $name  Request name in Postman
     * @param  string|null  $description  Request description
     * @param  int  $order  Order within folder (lower = first)
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly int $order = 0,
    ) {}
}
