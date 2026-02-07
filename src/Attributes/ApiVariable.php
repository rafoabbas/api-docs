<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiVariable
{
    /**
     * @param  string  $name  Variable name to set (e.g., BEARER_TOKEN)
     * @param  string  $path  JSON path to extract value (e.g., data.token)
     * @param  string  $scope  Variable scope: environment, collection, global
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $scope = 'environment',
    ) {}
}
