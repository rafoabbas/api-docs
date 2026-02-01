<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;
use Illuminate\Http\Resources\Json\JsonResource;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiResource
{
    /**
     * @param  class-string<JsonResource>  $resourceClass  The Resource class to use for response structure
     * @param  bool|null  $wrapped  Whether the response is wrapped in ApiResponse format (null = auto-detect from method body)
     * @param  int  $status  HTTP status code
     * @param  bool|null  $collection  Whether the response is a collection (null = auto-detect from method body)
     */
    public function __construct(
        public string $resourceClass,
        public ?bool $wrapped = null,
        public int $status = 200,
        public ?bool $collection = null,
    ) {}
}
