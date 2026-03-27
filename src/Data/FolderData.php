<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class FolderData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly int $order = 0,
    ) {}
}
