<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiDeprecated
{
    /**
     * @param  string|null  $reason  Reason for deprecation
     * @param  string|null  $replacement  Suggested replacement endpoint
     */
    public function __construct(
        public ?string $reason = null,
        public ?string $replacement = null,
    ) {}
}