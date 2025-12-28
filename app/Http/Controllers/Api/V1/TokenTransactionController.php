<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TokenTransactionHistoryRequest;
use App\Models\TokenTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TokenTransactionController extends Controller
{
    /**
     * Get user's token transaction history
     * 
     * Supports filtering by type and date range
     * Paginated (15 per page)
     */
    public function index(TokenTransactionHistoryRequest $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $query = TokenTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by type
        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        // Filter by date range
        if (isset($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $transactions = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get user's current token balance
     * 
     * Returns both stored balance and calculated balance from transactions
     * (for reconciliation)
     */
    public function balance(): JsonResponse
    {
        $user = auth()->user();

        // Calculate balance from transactions (source of truth)
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)
            ->sum('amount_tokens');

        // Get stored balance (for comparison)
        $storedBalance = (int) $user->tokens_balance;

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $storedBalance,
                'calculated_balance' => $calculatedBalance,
                'balance_match' => $storedBalance === $calculatedBalance,
            ],
        ]);
    }
}

