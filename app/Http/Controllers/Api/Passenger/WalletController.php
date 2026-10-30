<?php

namespace App\Http\Controllers\Api\Passenger;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends BaseController
{
    // ═══════════════════════════════════════════════════════════════
    // GET WALLET
    // GET /api/passenger/wallet
    // ═══════════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'balance' => $user->wallet_balance,
            'transactions' => $user->transactions()
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
        ], 'Wallet data');
    }
}