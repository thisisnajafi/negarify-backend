<?php

namespace Database\Seeders;

use App\Models\TokenBundle;
use Illuminate\Database\Seeder;

class TokenBundleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bundles = [
            [
                'name' => 'Starter Pack',
                'token_amount' => 100,
                'price_usd' => 1.00,
                'bonus_tokens' => 0,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'Standard Pack',
                'token_amount' => 500,
                'price_usd' => 4.50,
                'bonus_tokens' => 0,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Premium Pack',
                'token_amount' => 1000,
                'price_usd' => 8.00,
                'bonus_tokens' => 50,
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'Ultimate Pack',
                'token_amount' => 2000,
                'price_usd' => 15.00,
                'bonus_tokens' => 200,
                'is_active' => true,
                'display_order' => 4,
            ],
        ];

        foreach ($bundles as $bundle) {
            TokenBundle::firstOrCreate(
                ['name' => $bundle['name']],
                $bundle
            );
        }
    }
}

