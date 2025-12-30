<?php

namespace Database\Factories;

use App\Models\Model;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ModelFactory extends Factory
{
    protected $model = Model::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['image', 'video', 'audio'];
        $type = fake()->randomElement($types);

        return [
            'provider_id' => Provider::factory(),
            'model_name' => fake()->words(2, true) . ' ' . ucfirst($type) . ' Model',
            'model_type' => $type,
            'api_endpoint' => '/api/v1/generate/' . $type,
            'quality_profile' => fake()->randomElement(['standard', 'high', 'premium']),
            'base_cost_usd' => fake()->randomFloat(4, 0.01, 0.5),
            'default_tokens' => fake()->numberBetween(10, 100),
            'supports_size' => true,
            'supports_style' => true,
            'max_resolution' => '1024x1024',
            'enabled' => true,
        ];
    }

    /**
     * Indicate that the model is for images.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_type' => 'image',
            'api_endpoint' => '/api/v1/generate/image',
        ]);
    }

    /**
     * Indicate that the model is for videos.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_type' => 'video',
            'api_endpoint' => '/api/v1/generate/video',
        ]);
    }

    /**
     * Indicate that the model is for audio.
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_type' => 'audio',
            'api_endpoint' => '/api/v1/generate/audio',
        ]);
    }

    /**
     * Indicate that the model is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}



