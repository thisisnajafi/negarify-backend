<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenBundle;
use App\Models\TokenTransaction;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TokenController extends Controller
{
    /**
     * Get available token bundles
     */
    public function getBundles(): JsonResponse
    {
        $bundles = TokenBundle::active()
            ->ordered()
            ->get()
            ->map(function ($bundle) {
                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'tokens' => $bundle->tokens,
                    'price_usd' => $bundle->price_usd,
                    'effective_price' => $bundle->effective_price,
                    'discount_percentage' => $bundle->discount_percentage,
                    'savings' => $bundle->savings,
                    'description' => $bundle->description,
                    'is_popular' => $bundle->is_popular,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $bundles,
        ]);
    }

    /**
     * Purchase token bundle
     */
    public function purchase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bundle_id' => 'required|integer|exists:token_bundles,id',
            'payment_method' => 'required|string|in:stripe,paypal',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $bundle = TokenBundle::find($request->bundle_id);

        if (!$bundle->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Token bundle is not available',
            ], 400);
        }

        // Create order
        $order = Order::createOrder($user, $bundle, $request->payment_method);

        // TODO: Integrate with payment gateway (Stripe/PayPal)
        // For now, we'll simulate a successful payment
        $order->markAsCompleted('simulated_payment_id', [
            'payment_method' => $request->payment_method,
            'simulated' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tokens purchased successfully',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'tokens' => $order->tokens,
                    'amount_usd' => $order->amount_usd,
                    'status' => $order->status,
                    'completed_at' => $order->completed_at,
                ],
                'user' => [
                    'tokens_balance' => $user->fresh()->tokens_balance,
                ],
            ],
        ]);
    }

    /**
     * Get token transaction history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $transactions = TokenTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount_tokens' => $transaction->amount_tokens,
                    'amount_usd' => $transaction->amount_usd,
                    'description' => $transaction->description,
                    'reference_id' => $transaction->reference_id,
                    'created_at' => $transaction->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Get user token balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'tokens_balance' => $user->tokens_balance,
                'formatted_balance' => number_format($user->tokens_balance, 0),
            ],
        ]);
    }

    /**
     * Consume tokens (internal use)
     */
    public function consume(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user->hasTokens($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient token balance',
                'data' => [
                    'required' => $request->amount,
                    'available' => $user->tokens_balance,
                ],
            ], 400);
        }

        // Deduct tokens
        $success = $user->deductTokens($request->amount);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct tokens',
            ], 500);
        }

        // Create transaction record
        $transaction = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -$request->amount,
            'type' => 'consume',
            'reference_id' => $request->reference_id,
            'description' => $request->description,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tokens consumed successfully',
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'amount_tokens' => $transaction->amount_tokens,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                ],
                'user' => [
                    'tokens_balance' => $user->fresh()->tokens_balance,
                ],
            ],
        ]);
    }

    /**
     * Add tokens (admin use)
     */
    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Add tokens
        $success = $user->addTokens($request->amount);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add tokens',
            ], 500);
        }

        // Create transaction record
        $transaction = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => $request->amount,
            'type' => 'bonus',
            'reference_id' => $request->reference_id,
            'description' => $request->description,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tokens added successfully',
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'amount_tokens' => $transaction->amount_tokens,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                ],
                'user' => [
                    'tokens_balance' => $user->fresh()->tokens_balance,
                ],
            ],
        ]);
    }
}
