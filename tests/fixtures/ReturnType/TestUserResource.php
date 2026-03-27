<?php

declare(strict_types=1);

namespace ApiDocs\Tests\Fixtures\ReturnType;

use Illuminate\Http\Resources\Json\JsonResource;

class TestUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
