<?php

namespace Database\Seeders;

use App\Models\Model as AiModel;
use App\Models\Provider;
use Illuminate\Database\Seeder;

class ModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $segmindProvider = Provider::where('name', 'Segmind')->first();

        if (!$segmindProvider) {
            $this->command->warn('Segmind provider not found. Please run ProviderSeeder first.');
            return;
        }

        // Sample AI models from Segmind (placeholder - update with actual Segmind models)
        $models = [
            // Image models
            [
                'provider_id' => $segmindProvider->id,
                'model_name' => 'flux-dev',
                'model_type' => 'image',
                'api_endpoint' => '/v1/flux-dev',
                'quality_profile' => 'hd',
                'base_cost_usd' => 0.01,
                'default_tokens' => 10,
                'supports_size' => true,
                'supports_style' => true,
                'max_resolution' => '1024x1024',
                'enabled' => true,
            ],
            [
                'provider_id' => $segmindProvider->id,
                'model_name' => 'stable-diffusion-xl',
                'model_type' => 'image',
                'api_endpoint' => '/v1/stable-diffusion-xl',
                'quality_profile' => 'standard',
                'base_cost_usd' => 0.008,
                'default_tokens' => 8,
                'supports_size' => true,
                'supports_style' => false,
                'max_resolution' => '1024x1024',
                'enabled' => true,
            ],
            // Video models (placeholder)
            [
                'provider_id' => $segmindProvider->id,
                'model_name' => 'video-generation-v1',
                'model_type' => 'video',
                'api_endpoint' => '/v1/video-generation',
                'quality_profile' => 'standard',
                'base_cost_usd' => 0.05,
                'default_tokens' => 50,
                'supports_size' => true,
                'supports_style' => false,
                'max_resolution' => '720p',
                'enabled' => true,
            ],
            // Audio models (placeholder)
            [
                'provider_id' => $segmindProvider->id,
                'model_name' => 'audio-generation-v1',
                'model_type' => 'audio',
                'api_endpoint' => '/v1/audio-generation',
                'quality_profile' => 'standard',
                'base_cost_usd' => 0.02,
                'default_tokens' => 20,
                'supports_size' => false,
                'supports_style' => false,
                'max_resolution' => null,
                'enabled' => true,
            ],
        ];

        foreach ($models as $model) {
            AiModel::firstOrCreate(
                [
                    'provider_id' => $model['provider_id'],
                    'model_name' => $model['model_name'],
                ],
                $model
            );
        }
    }
}

