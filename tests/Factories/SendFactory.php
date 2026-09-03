<?php

namespace Tests\Factories;

use App\Models\Send;
use App\Models\User;
use Illuminate\Support\Str;

class SendFactory
{
    /**
     * Persist a send in the database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(User|int $user, array $attributes = []): Send
    {
        return Send::forceCreate(self::attributes($user, $attributes));
    }

    /**
     * Build a send model without persisting it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function make(User|int $user = 1, array $attributes = []): Send
    {
        return (new Send)->forceFill(self::attributes($user, $attributes, persisted: false));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function attributes(User|int $user, array $attributes = [], bool $persisted = true): array
    {
        $userId = $user instanceof User ? $user->id : $user;

        $merged = array_merge([
            'user_id' => $userId,
            'message' => fake()->sentence(),
            'name' => fake()->words(3, true),
            'valid_to' => now()->addDay(),
        ], $attributes);

        if (! $persisted) {
            return [
                'id' => $merged['id'] ?? (string) Str::uuid7(),
                'user_id' => $merged['user_id'],
                'message' => $merged['message'],
                'name' => $merged['name'],
                'valid_to' => $merged['valid_to'],
                'public_id' => $merged['public_id'] ?? (string) Str::uuid(),
            ];
        }

        return $merged;
    }
}
