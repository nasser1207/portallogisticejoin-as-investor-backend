<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/portallogistice/dashboard
     * Returns user, real investment summary, contracts summary, and next payment.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // ── load user's approved contracts with their payments ─────────────────
      $contracts = Contract::with('payments')
            ->where('user_id', $user->id)
            ->where('status', Contract::STATUS_APPROVED)
            ->where('type', Contract::TYPE_RENTAL)
            ->where('monthly_payment_amount', '>', 0)
            ->get();

        // ── investment totals ─────────────────────────────────────────────────
        $totalInvestment  = (float) $contracts->sum('total_amount');
        $monthlyDeposit   = (float) $contracts->sum('monthly_payment_amount');

        // Collect all payments across all contracts
        $allPayments = $contracts->flatMap(fn ($c) => $c->payments);

        $totalReceived = (float) $allPayments
            ->where('status', Payment::STATUS_RECEIVED)
            ->sum('amount');

        $totalPending = (float) $allPayments
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SENT])
            ->sum('amount');

        // How many months have had at least one received payment (progress indicator)
        $monthsPassed = $allPayments
            ->where('status', Payment::STATUS_RECEIVED)
            ->unique('month_number')
            ->count();

        // ── next upcoming payment ─────────────────────────────────────────────
        $nextPayment = $allPayments
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SENT])
            ->sortBy('due_date')
            ->first();

        $nextPaymentData = null;
        if ($nextPayment) {
            $daysRemaining = (int) now()->startOfDay()->diffInDays(
                \Carbon\Carbon::parse($nextPayment->due_date)->startOfDay(),
                false
            );
            $nextPaymentData = [
                'amount'        => (float) $nextPayment->amount,
                'due_date'      => $nextPayment->due_date?->toDateString(),
                'days_remaining'=> $daysRemaining,
                'contract_id'   => $nextPayment->contract_id,
            ];
        }

        // ── contracts summary ─────────────────────────────────────────────────
       $allUserContracts = Contract::where('user_id', $user->id) ->where('type', Contract::TYPE_RENTAL)->get();

        $contractSummary = [
            'total'    => $allUserContracts->count(),
            'approved' => $contracts->count(),
            'pending'  => $allUserContracts->whereIn('status', [
                Contract::STATUS_SENT,
                Contract::STATUS_NAFATH_PENDING,
                Contract::STATUS_NAFATH_APPROVED,
                Contract::STATUS_ADMIN_PENDING,
            ])->count(),
            'activated' => $contracts->filter(fn ($c) => $c->isActivated())->count(),
        ];

        // Contract start months (month index of activation date) for timeline chart
        $contractStartMonths = $contracts
            ->filter(fn ($c) => $c->activationDate() !== null)
            ->map(fn ($c) => (int) $c->activationDate()->format('n')) // 1–12
            ->values()
            ->toArray();

        // ── payment status breakdown ──────────────────────────────────────────
        $paymentStatus = [
            'total'            => $allPayments->count(),
            'received'         => $allPayments->where('status', Payment::STATUS_RECEIVED)->count(),
            'pending'          => $allPayments->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SENT])->count(),
            'reported_missing' => $allPayments->where('status', Payment::STATUS_REPORTED_MISSING)->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => $user->toApiArray(),

                'investment' => [
                    'total'               => $totalInvestment,
                    'monthlyDeposit'      => $monthlyDeposit,
                    'totalReceived'       => $totalReceived,
                    'totalPending'        => $totalPending,
                    'monthsPassed'        => $monthsPassed,
                    'contractStartMonths' => $contractStartMonths,
                    'payoutCycle'         => 1, // monthly
                ],

                'contracts'     => $contractSummary,
                'paymentStatus' => $paymentStatus,
                'nextPayment'   => $nextPaymentData,
            ],
        ]);
    }
}
