<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ApiFolder
{
    /**
     * @param  string  $name  Folder name (supports nested: "Auth / OTP")
     * @param  string|null  $description  Folder description
     * @param  int  $order  Order of folder (lower = first)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly int $order = 0,
    ) {}
}
