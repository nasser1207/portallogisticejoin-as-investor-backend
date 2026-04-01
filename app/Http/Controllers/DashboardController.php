<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/portallogistice/dashboard
     * Returns user, investment summary, and contracts summary for the dashboard.
     * Protected: auth.token (Bearer).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // TODO: replace with real data from contracts/investments tables when available
        $investment = [
            'total' => 6600,
            'monthlyDeposit' => 660,
            'monthsPassed' => 3,
            'status' => 'active',
            'payoutCycle' => 3,
            'monthlyMaintenance' => 125,
            'contractStartMonths' => [1, 4, 6, 11, 12],
        ];

        $contracts = [
            'max' => 10,
            'unlocked' => 3,
            'active' => 2,
            'used' => 2,
            'ended' => 0,
            'renewed' => 0,
            'renewalNotifications' => 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toApiArray(),
                'investment' => $investment,
                'contracts' => $contracts,
            ],
        ]);
    }
}
