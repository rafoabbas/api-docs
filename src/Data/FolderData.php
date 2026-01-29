<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class FolderData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public int $order = 0,
    ) {}
}
