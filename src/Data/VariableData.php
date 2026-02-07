<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class VariableData
{
    public function __construct(
        public string $name,
        public string $path,
        public string $scope = 'environment',
    ) {}
}
