<?php

declare(strict_types=1);

namespace ApiDocs\Generators;

use Illuminate\Support\Str;

final class EnvironmentGenerator
{
    /**
     * Generate a Postman environment file.
     *
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    public function generate(string $name, array $variables): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'values' => $this->buildValues($variables),
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toIso8601String(),
            '_postman_exported_using' => 'ApiDocs/1.0',
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return array<int, array<string, mixed>>
     */
    private function buildValues(array $variables): array
    {
        $values = [];

        foreach ($variables as $key => $value) {
            $values[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'default',
                'enabled' => true,
            ];
        }

        return $values;
    }
}
