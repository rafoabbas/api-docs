<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiHidden
{
    public function __construct() {}
}
