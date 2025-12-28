<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Segmind provider entry
        Provider::firstOrCreate(
            ['name' => 'Segmind'],
            [
                'api_base_url' => 'https://api.segmind.com',
                'api_key_encrypted' => Crypt::encryptString(''), // Set actual API key in .env
                'cost_per_image_usd' => null, // To be configured based on actual Segmind pricing
                'cost_per_video_usd' => null,
                'cost_per_audio_usd' => null,
                'enabled' => true,
            ]
        );
    }
}

