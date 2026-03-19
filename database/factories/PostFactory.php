<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'body'       => fake('ru_RU')->realText(fake()->numberBetween(40, 350)),
            'photo_path' => null,
        ];
    }
}
