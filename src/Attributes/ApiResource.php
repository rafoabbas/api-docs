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
     * @param  bool  $wrapped  Whether the response is wrapped in ApiResponse format
     * @param  int  $status  HTTP status code
     */
    public function __construct(
        public string $resourceClass,
        public bool $wrapped = true,
        public int $status = 200,
    ) {}
}
