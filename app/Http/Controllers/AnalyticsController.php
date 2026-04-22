<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * GET /portallogistice/analytics/summary
     * KPI cards: total invested, total received, pending, contract counts.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $contracts   = Contract::with('payments')
            ->where('user_id', $user->id)
            ->where('status', Contract::STATUS_APPROVED)
            ->where('type', Contract::TYPE_RENTAL)
            ->where('monthly_payment_amount', '>', 0)
            ->get();

        $allPayments = $contracts->flatMap(fn ($c) => $c->payments);

        $totalReceived = (float) $allPayments->where('status', Payment::STATUS_RECEIVED)->sum('amount');
        $totalPending  = (float) $allPayments
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SENT])
            ->sum('amount');

        // Next due payment
        $next = $allPayments
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SENT])
            ->sortBy('due_date')
            ->first();

        $nextPayment = null;
        if ($next) {
            $days = (int) now()->startOfDay()->diffInDays(
                \Carbon\Carbon::parse($next->due_date)->startOfDay(), false
            );
            $nextPayment = [
                'amount'         => (float) $next->amount,
                'due_date'       => $next->due_date?->toDateString(),
                'days_remaining' => $days,
            ];
        }

        // Completion rate = received / total payments
        $total = $allPayments->count();
        $completionRate = $total > 0
            ? round(($allPayments->where('status', Payment::STATUS_RECEIVED)->count() / $total) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'total_contracts'   => $contracts->count(),
                'active_contracts'  => $contracts->filter(fn ($c) => $c->isActivated())->count(),
                'pending_contracts' => Contract::where('user_id', $user->id)->where('type', Contract::TYPE_RENTAL)
                    ->whereIn('status', [
                        Contract::STATUS_SENT,
                        Contract::STATUS_NAFATH_PENDING,
                        Contract::STATUS_NAFATH_APPROVED,
                        Contract::STATUS_ADMIN_PENDING,
                    ])->count(),
                'total_invested'    => $contracts->sum('total_amount'),
                'total_received'    => $totalReceived,
                'pending_payments'  => $totalPending,
                'completion_rate'   => $completionRate,
                'next_payment'      => $nextPayment,
            ],
        ]);
    }

    /**
     * GET /portallogistice/analytics/payments
     * Monthly payment trend for the bar/line chart.
     * Returns data for each of the 12 months of the current year.
     */
    public function payments(Request $request): JsonResponse
    {
        $user = $request->user();

        $contracts = Contract::where('user_id', $user->id)
            ->where('status', Contract::STATUS_APPROVED)
             ->where('type', Contract::TYPE_RENTAL)
             ->where('monthly_payment_amount', '>', 0)
            ->pluck('id');

        // Group received payments by month of payment_date
        $received = Payment::whereIn('contract_id', $contracts)
            ->where('status', Payment::STATUS_RECEIVED)
            ->whereYear('payment_date', now()->year)
            ->get()
            ->groupBy(fn ($p) => (int) $p->payment_date->format('n')); // 1-12

        // Group pending payments by month of due_date
        $pending = Payment::whereIn('contract_id', $contracts)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SENT])
            ->whereYear('due_date', now()->year)
            ->get()
            ->groupBy(fn ($p) => (int) $p->due_date->format('n'));

        $monthNamesAr = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو',  6 => 'يونيو',  7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر',10 => 'أكتوبر',11 => 'نوفمبر',12 => 'ديسمبر',
        ];
        $monthNamesEn = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month'          => $m,
                'month_name'     => $monthNamesEn[$m],
                'month_name_ar'  => $monthNamesAr[$m],
                'total_amount'   => (float) ($received[$m] ?? collect())->sum('amount'),
                'pending_amount' => (float) ($pending[$m] ?? collect())->sum('amount'),
                'count_received' => ($received[$m] ?? collect())->count(),
                'count_pending'  => ($pending[$m] ?? collect())->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'year'         => now()->year,
                'monthly_data' => $monthly,
            ],
        ]);
    }
}
