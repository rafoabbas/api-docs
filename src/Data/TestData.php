<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class TestData
{
    public function __construct(
        public string $script,
        public ?string $name = null,
    ) {}
}
