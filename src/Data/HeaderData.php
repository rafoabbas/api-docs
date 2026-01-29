<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class HeaderData
{
    public function __construct(
        public string $key,
        public string $value,
        public ?string $description = null,
        public bool $disabled = false,
    ) {}
}
