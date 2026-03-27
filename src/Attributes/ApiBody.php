<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiBody
{
    public const MODE_RAW = 'raw';

    public const MODE_FORMDATA = 'formdata';

    public const MODE_URLENCODED = 'urlencoded';

    /**
     * @param  array<string, mixed>  $data  Request body data
     * @param  string  $mode  Body mode: raw, formdata, urlencoded
     * @param  string  $language  Language for raw mode: json, xml, text
     * @param  bool  $merge  Merge with auto-resolved FormRequest body (true) or replace entirely (false)
     * @param  array<string>  $except  Keys to exclude from auto-resolved FormRequest body
     */
    public function __construct(
        public readonly array $data = [],
        public readonly string $mode = self::MODE_RAW,
        public readonly string $language = 'json',
        public readonly bool $merge = false,
        public readonly array $except = [],
    ) {}
}
