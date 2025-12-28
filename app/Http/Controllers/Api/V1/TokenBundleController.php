<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTokenBundleRequest;
use App\Http\Requests\Api\V1\UpdateTokenBundleRequest;
use App\Models\TokenBundle;
use App\Services\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TokenBundleController extends Controller
{
    public function __construct(
        private readonly CurrencyRateService $currencyRateService
    ) {
    }

    /**
     * List all active token bundles with real-time prices
     * 
     * Public endpoint - no authentication required
     */
    public function index(): JsonResponse
    {
        // Get current USD to Toman rate
        $dollarRate = $this->currencyRateService->getCurrentRateWithFallback();

        // Get active bundles ordered by display_order
        $bundles = TokenBundle::active()
            ->ordered()
            ->get()
            ->map(function ($bundle) use ($dollarRate) {
                // Calculate price in Toman (server-side, never from client)
                $priceToman = (float) $bundle->price_usd * $dollarRate;

                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'token_amount' => $bundle->token_amount,
                    'bonus_tokens' => $bundle->bonus_tokens,
                    'total_tokens' => $bundle->total_tokens,
                    'price_usd' => (float) $bundle->price_usd,
                    'price_toman' => round($priceToman, 2),
                    'dollar_rate' => $dollarRate,
                    'is_active' => $bundle->is_active,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $bundles,
            'meta' => [
                'dollar_rate' => $dollarRate,
                'rate_source' => 'tgju',
            ],
        ]);
    }

    /**
     * Store a new token bundle (Admin only)
     */
    public function store(StoreTokenBundleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $bundle = TokenBundle::create([
            'name' => $validated['name'],
            'token_amount' => $validated['token_amount'],
            'price_usd' => $validated['price_usd'],
            'bonus_tokens' => $validated['bonus_tokens'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'display_order' => $validated['display_order'] ?? 0,
        ]);

        Log::info('Token bundle created', [
            'bundle_id' => $bundle->id,
            'name' => $bundle->name,
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token bundle created successfully',
            'data' => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'token_amount' => $bundle->token_amount,
                'bonus_tokens' => $bundle->bonus_tokens,
                'price_usd' => (float) $bundle->price_usd,
                'is_active' => $bundle->is_active,
                'display_order' => $bundle->display_order,
            ],
        ], 201);
    }

    /**
     * Update a token bundle (Admin only)
     */
    public function update(UpdateTokenBundleRequest $request, int $id): JsonResponse
    {
        $bundle = TokenBundle::findOrFail($id);
        $validated = $request->validated();

        // Update only provided fields
        if (isset($validated['name'])) {
            $bundle->name = $validated['name'];
        }
        if (isset($validated['token_amount'])) {
            $bundle->token_amount = $validated['token_amount'];
        }
        if (isset($validated['price_usd'])) {
            $bundle->price_usd = $validated['price_usd'];
        }
        if (isset($validated['bonus_tokens'])) {
            $bundle->bonus_tokens = $validated['bonus_tokens'];
        }
        if (isset($validated['is_active'])) {
            $bundle->is_active = $validated['is_active'];
        }
        if (isset($validated['display_order'])) {
            $bundle->display_order = $validated['display_order'];
        }

        $bundle->save();

        Log::info('Token bundle updated', [
            'bundle_id' => $bundle->id,
            'updated_fields' => array_keys($validated),
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token bundle updated successfully',
            'data' => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'token_amount' => $bundle->token_amount,
                'bonus_tokens' => $bundle->bonus_tokens,
                'price_usd' => (float) $bundle->price_usd,
                'is_active' => $bundle->is_active,
                'display_order' => $bundle->display_order,
            ],
        ]);
    }

    /**
     * Delete a token bundle (Admin only)
     * 
     * Note: Soft delete preferred, but for now using hard delete
     * Consider adding soft deletes if bundles have order history
     */
    public function destroy(int $id): JsonResponse
    {
        $bundle = TokenBundle::findOrFail($id);

        // Check if bundle has orders (prevent deletion if has orders)
        if ($bundle->orders()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bundle with existing orders',
            ], 400);
        }

        $bundle->delete();

        Log::info('Token bundle deleted', [
            'bundle_id' => $id,
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token bundle deleted successfully',
        ]);
    }

    /**
     * Activate a token bundle (Admin only)
     */
    public function activate(int $id): JsonResponse
    {
        $bundle = TokenBundle::findOrFail($id);

        if ($bundle->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'Bundle is already active',
                'data' => [
                    'id' => $bundle->id,
                    'is_active' => true,
                ],
            ]);
        }

        $bundle->is_active = true;
        $bundle->save();

        Log::info('Token bundle activated', [
            'bundle_id' => $id,
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token bundle activated successfully',
            'data' => [
                'id' => $bundle->id,
                'is_active' => true,
            ],
        ]);
    }

    /**
     * Deactivate a token bundle (Admin only)
     */
    public function deactivate(int $id): JsonResponse
    {
        $bundle = TokenBundle::findOrFail($id);

        if (!$bundle->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'Bundle is already inactive',
                'data' => [
                    'id' => $bundle->id,
                    'is_active' => false,
                ],
            ]);
        }

        $bundle->is_active = false;
        $bundle->save();

        Log::info('Token bundle deactivated', [
            'bundle_id' => $id,
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token bundle deactivated successfully',
            'data' => [
                'id' => $bundle->id,
                'is_active' => false,
            ],
        ]);
    }
}

