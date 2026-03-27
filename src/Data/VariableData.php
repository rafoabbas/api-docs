<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class VariableData
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $scope = 'environment',
    ) {}
}
