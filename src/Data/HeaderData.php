<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class HeaderData
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly ?string $description = null,
        public readonly bool $disabled = false,
    ) {}
}
