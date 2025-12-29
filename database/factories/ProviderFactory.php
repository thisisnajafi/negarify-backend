<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Provider',
            'api_base_url' => 'https://api.example.com',
            'api_key_encrypted' => encrypt('test-api-key'),
            'cost_per_image_usd' => fake()->randomFloat(4, 0.01, 1.0),
            'cost_per_video_usd' => fake()->randomFloat(4, 0.01, 2.0),
            'cost_per_audio_usd' => fake()->randomFloat(4, 0.01, 1.5),
            'enabled' => true,
        ];
    }

    /**
     * Indicate that the provider is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}

