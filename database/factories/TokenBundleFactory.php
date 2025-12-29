<?php

namespace Database\Factories;

use App\Models\TokenBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

class TokenBundleFactory extends Factory
{
    protected $model = TokenBundle::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Pack',
            'token_amount' => $this->faker->randomElement([100, 500, 1000, 2000, 5000]),
            'price_usd' => $this->faker->randomFloat(2, 0.50, 50.00),
            'bonus_tokens' => $this->faker->numberBetween(0, 200),
            'is_active' => true,
            'display_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}

