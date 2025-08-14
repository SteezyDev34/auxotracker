<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'user_lastname' => fake()->lastName(),
            'user_firstname' => fake()->firstName(),
            'user_profile_picture' => 'user.png',
            'user_banner_picture' => 'cover.jpg',
            'user_level' => 'debutant',
            'user_birthdate' => fake()->date(),
            'user_language' => fake()->randomElement(['fr', 'en']),
            'user_currency' => fake()->randomElement(['EUR', 'USD']),
            'user_sort_bets_by' => null,
            'user_welcome_page' => '1',
            'user_bookmaker_list' => null,
            'user_sport_list' => null,
            'role' => 'user',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
