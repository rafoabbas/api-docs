<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use Illuminate\Support\Str;

final class ExampleValueGenerator
{
    /**
     * Guess a string value based on field name and validation rules.
     *
     * @param  array<int, mixed>  $rules
     */
    public function string(string $field, array $rules = []): string
    {
        $fieldLower = Str::lower($field);
        $fieldSnake = Str::snake($field);

        $patterns = [
            'email' => 'user@example.com',
            'phone' => '+905551234567',
            'password' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'name' => 'John Doe',
            'title' => 'Example Title',
            'description' => 'Example description text',
            'address' => '123 Main Street',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'zip' => '34000',
            'postal_code' => '34000',
            'url' => 'https://example.com',
            'website' => 'https://example.com',
            'token' => 'abc123token',
            'session_token' => 'session_abc123',
            'otp' => '123456',
            'otp_code' => '123456',
            'code' => '123456',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'order_quote_id' => '550e8400-e29b-41d4-a716-446655440000',
            'query' => 'search term',
            'search' => 'search term',
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
        ];

        foreach ($patterns as $pattern => $value) {
            if (Str::contains($fieldLower, $pattern) || Str::contains($fieldSnake, $pattern)) {
                return $value;
            }
        }

        // Check for enum (has 'in:' rule)
        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'in:')) {
                $options = explode(',', Str::after($rule, 'in:'));

                return $options[0] ?? 'value';
            }
        }

        return 'string_value';
    }

    /**
     * Guess an integer value based on field name.
     */
    public function integer(string $field): int
    {
        $fieldLower = Str::lower($field);

        if (Str::contains($fieldLower, ['id', 'count', 'quantity', 'qty'])) {
            return 1;
        }

        if (Str::contains($fieldLower, ['per_page', 'limit'])) {
            return 10;
        }

        if (Str::contains($fieldLower, ['page'])) {
            return 1;
        }

        if (Str::contains($fieldLower, ['age'])) {
            return 25;
        }

        return 1;
    }

    /**
     * Guess a numeric value based on field name.
     */
    public function numeric(string $field): float|int
    {
        $fieldLower = Str::lower($field);

        if (Str::contains($fieldLower, ['price', 'amount', 'total', 'cost'])) {
            return 99.99;
        }

        if (Str::contains($fieldLower, ['lat', 'latitude'])) {
            return 41.0082;
        }

        if (Str::contains($fieldLower, ['lng', 'lon', 'longitude'])) {
            return 28.9784;
        }

        if (Str::contains($fieldLower, ['percent', 'rate'])) {
            return 10.5;
        }

        return 0;
    }
}
