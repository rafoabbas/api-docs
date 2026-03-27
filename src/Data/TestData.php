<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class TestData
{
    public function __construct(
        public readonly string $script,
        public readonly ?string $name = null,
    ) {}
}
