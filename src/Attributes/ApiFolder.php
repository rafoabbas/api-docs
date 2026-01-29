<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiFolder
{
    /**
     * @param  string  $name  Folder name (supports nested: "Auth / OTP")
     * @param  string|null  $description  Folder description
     * @param  int  $order  Order of folder (lower = first)
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public int $order = 0,
    ) {}
}
