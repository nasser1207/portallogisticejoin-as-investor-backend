<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    // ── index — admin: all payments with filters ───────────────────────────────
    //
    // GET /admin/payments
    // Query params:
    //   status       = pending|sent|received|reported_missing
    //   due_date     = YYYY-MM-DD  (exact match)
    //   due_from     = YYYY-MM-DD  (range start)
    //   due_to       = YYYY-MM-DD  (range end)
    //   contract_id  = integer
    //   user_id      = integer (contract owner; payments → contracts.user_id)
    //   per_page     = integer (default 50)

    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['contract.user'])
            ->orderBy('due_date')
            ->orderBy('id');
        $status     = (string) $request->query('status', '');
        $dueDate    = (string) $request->query('due_date', '');
        $dueFrom    = (string) $request->query('due_from', '');
        $dueTo      = (string) $request->query('due_to', '');
        $contractId = (int)    $request->query('contract_id', 0);
        $userId     = (int)    $request->query('user_id', 0);
        $perPage    = min((int) $request->query('per_page', 50), 200);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($dueDate !== '') {
            $query->whereDate('due_date', $dueDate);
        } elseif ($dueFrom !== '' || $dueTo !== '') {
            if ($dueFrom !== '') $query->whereDate('due_date', '>=', $dueFrom);
            if ($dueTo   !== '') $query->whereDate('due_date', '<=', $dueTo);
        }

        if ($contractId > 0) {
            $query->where('contract_id', $contractId);
        }

        if ($userId > 0) {
            $query->whereHas('contract', fn ($q) => $q->where('user_id', $userId));
        }
             $query->whereHas('contract', fn ($q) => $q->where('type', Contract::TYPE_RENTAL));

        // ── Summary stats — run on the same filtered query (no pagination) ────────
        $statsQuery = clone $query;

        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $weekEnd  = now()->addDays(7)->toDateString();

        $allPayments = $statsQuery->get();

        $pendingCount  = $allPayments->where('status', Payment::STATUS_PENDING)->count();
        $receivedCount = $allPayments->where('status', Payment::STATUS_RECEIVED)->count();
        $overdueCount  = $allPayments
            ->where('status', Payment::STATUS_PENDING) 
            ->filter(fn (Payment $p) => $p->due_date?->toDateString() < $today)
            ->count();
        $totalAmount   = $allPayments->sum('amount');

        // Today
        $todayPayments       = $allPayments->filter(fn (Payment $p) => $p->due_date?->toDateString() === $today);
        $totalAmountToday    = $todayPayments->sum('amount');
        $contractsCountToday = $todayPayments->pluck('contract_id')->unique()->count();

        // Tomorrow
        $tomorrowPayments       = $allPayments->filter(fn (Payment $p) => $p->due_date?->toDateString() === $tomorrow);
        $totalAmountTomorrow    = $tomorrowPayments->sum('amount');
        $contractsCountTomorrow = $tomorrowPayments->pluck('contract_id')->unique()->count();

        // This week (today → end of week)
        $weekPayments       = $allPayments->filter(fn (Payment $p) => $p->due_date?->toDateString() >= $today && $p->due_date?->toDateString() <= $weekEnd);
        $totalAmountWeek    = $weekPayments->sum('amount');
        $contractsCountWeek = $weekPayments->pluck('contract_id')->unique()->count();
        // ─────────────────────────────────────────────────────────────────────────

        $paginated = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'pending_count'  => $pendingCount,
                    'received_count' => $receivedCount,
                    'overdue_count'  => $overdueCount,
                    'total_amount'   => (float) $totalAmount,
                    'today' => [
                        'total_amount'    => (float) $totalAmountToday,
                        'contracts_count' => $contractsCountToday,
                    ],
                    'tomorrow' => [
                        'total_amount'    => (float) $totalAmountTomorrow,
                        'contracts_count' => $contractsCountTomorrow,
                    ],
                    'this_week' => [
                        'total_amount'    => (float) $totalAmountWeek,
                        'contracts_count' => $contractsCountWeek,
                    ],
                ],
                'payments'    => $paginated->map(fn (Payment $p) => $this->toApiWithContract($p)),
                'pagination'  => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ── uploadReceipt — admin uploads receipt for a payment row ───────────────
    //
    // POST /admin/payments/{id}/receipt
    // Body: multipart/form-data  →  receipt: file

    public function uploadReceipt(Request $request, int $id): JsonResponse
    {
        $payment = Payment::with('contract')->findOrFail($id);

        $request->validate([
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        // Delete old receipt if exists
        if ($payment->receipt_path && Storage::disk('public')->exists($payment->receipt_path)) {
            Storage::disk('public')->delete($payment->receipt_path);
        }

        $path = $request->file('receipt')->store('payments/receipts', 'public');

        $payment->update([
            'receipt_path' => $path,
            'status'       => Payment::STATUS_RECEIVED,
            'payment_date' => now()->toDateString(),
        ]);

        Log::info('Payment receipt uploaded by admin', [
            'payment_id'  => $payment->id,
            'contract_id' => $payment->contract_id,
            'admin_id'    => $request->user()?->id,
            'path'        => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الإيصال وتحديث حالة الدفعة إلى مستلم.',
            'data'    => $this->toApiWithContract($payment->fresh('contract')),
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function toApiWithContract(Payment $payment): array
    {
        $contract = $payment->contract;
        $user     = $contract?->user;

        return [
            'id'           => $payment->id,
            'contract_id'  => $payment->contract_id,
            'month_number' => $payment->month_number,
            'amount'       => (float) $payment->amount,
            'due_date'     => $payment->due_date?->toDateString(),
            'payment_date' => $payment->payment_date?->toDateString(),
            'receipt_path' => $payment->receipt_path,
            'receipt_url'  => $payment->receipt_path
                                ? Storage::disk('public')->url($payment->receipt_path)
                                : null,
            'status'       => $payment->status,
            'contract'     => $contract ? [
                'id'    => $contract->id,
                'title' => $contract->title,
                'type'  => $contract->type,
            ] : null,
            'user' => $user ? [
                'id'   => $user->id,
                'name' => $user->name,
                'national_id' => $user->national_id,
            ] : null,
            'updated_at' => $payment->updated_at?->toIso8601String(),
        ];
    }
}