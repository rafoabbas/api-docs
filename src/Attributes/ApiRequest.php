<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiRequest
{
    /**
     * @param  string|null  $name  Request name in Postman
     * @param  string|null  $description  Request description
     * @param  int  $order  Order within folder (lower = first)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public int $order = 0,
    ) {}
}
