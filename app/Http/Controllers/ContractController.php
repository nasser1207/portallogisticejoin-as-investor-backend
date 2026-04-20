<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Services\SadqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\NafathSigningPipeline;
class ContractController extends Controller
{
   public function __construct(
        protected SadqService $sadqService,
        protected NafathSigningPipeline $pipeline,
    ) {}

    // ── index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Contract::with(['user', 'admin'])->latest('id');

        if (! $this->isAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        $status = (string) $request->query('status', '');
        $type   = (string) $request->query('type', '');
        $userId = (int)    $request->query('user_id', 0);

        if ($status !== '') {
            if ($status === 'admin_pending') {
                $query->whereIn('status', [
                    Contract::STATUS_ADMIN_PENDING,
                    Contract::STATUS_NAFATH_APPROVED,
                    Contract::STATUS_RECEIPT_REVIEW,  // also show receipt_review in admin_pending tab
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($this->isAdmin($user) && $userId > 0) {
            $query->where('user_id', $userId);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn (Contract $c) => $this->toApi($c)),
        ]);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::with(['user', 'admin'])->findOrFail($id);

        if (! $this->isAdmin($user) && $contract->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->toApi($contract)]);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'                => 'nullable|integer|exists:users,id|required_without:national_id',
            'national_id'            => 'nullable|string|max:32|required_without:user_id',
            'type'                   => 'required|in:sale,rental',
            'title'                  => 'required|string|max:255',
            'file'                   => 'nullable|file|mimes:pdf|max:15360',
            'total_amount'           => 'nullable|numeric|min:0',
            'monthly_payment_amount' => 'nullable|numeric|min:0',
        ]);

        $targetUser = null;
        if (! empty($validated['user_id'])) {
            $targetUser = User::find($validated['user_id']);
        } elseif (! empty($validated['national_id'])) {
            $targetUser = User::where('national_id', $validated['national_id'])->first();
        }

        if (! $targetUser) {
            return response()->json(['success' => false, 'message' => 'Assigned user not found.'], 422);
        }
        if ($targetUser->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Contracts can be assigned to users only.'], 422);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('contracts', 'public');
        }

        $contract = Contract::create([
            'user_id'                => $targetUser->id,
            'type'                   => $validated['type'],
            'title'                  => $validated['title'],
            'file_path'              => $filePath,
            'status'                 => Contract::STATUS_DRAFT,
            'total_amount'           => $validated['total_amount'] ?? null,
            'monthly_payment_amount' => $validated['monthly_payment_amount'] ?? null,
            'total_amount_paid'      => 0,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
        ], 201);
    }

    // ── send ──────────────────────────────────────────────────────────────────

    public function send(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        if (! in_array($contract->status, [Contract::STATUS_DRAFT, Contract::STATUS_REJECTED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن إرسال العقد فقط من حالة مسودة أو مرفوض.',
            ], 422);
        }

        $contract->update(['status' => Contract::STATUS_SENT]);

        return response()->json([
            'success' => true,
            'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    // ── nafath ────────────────────────────────────────────────────────────────

    public function nafath(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::with('user')->findOrFail($id);

        if ($contract->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if (! in_array($contract->status, [Contract::STATUS_SENT, Contract::STATUS_NAFATH_PENDING], true)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن بدء التوثيق إلا للعقود المُرسَلة.',
            ], 422);
        }

        if ((string) ($contract->user?->national_id ?? '') === '') {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهوية الوطنية مطلوب للتوثيق عبر نفاذ.',
            ], 422);
        }

        $result = $this->pipeline->initiate($contract);
        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'فشل بدء التوثيق عبر نفاذ.',
                'sadq'    => $result,
            ], 422);
        }
//         $contract->update(['status' => Contract::STATUS_ADMIN_PENDING]);

//         $contract->refresh();
// return response()->json([
//     'success' => true,
//     'message' => 'تم إرسال الطلب إلى تطبيق نفاذ 📱 يرجى فتح التطبيق واختيار الرقم للموافقة',
//     'data' => $this->toApi($contract->fresh(['user', 'admin'])),
// ]);
        return response()->json([
            'success'          => true,
            'message'          => 'تم إرسال الطلب إلى تطبيق نفاذ 📱 يرجى فتح التطبيق واختيار الرقم للموافقة',
            'challenge_number' => $result['challenge_number'] ?? null,
            'data'             => $this->toApi($contract->fresh(['user', 'admin'])),
            'sadq'             => $result,
        ]);
    }

    // ── adminApprove ──────────────────────────────────────────────────────────
    //
    // RENTAL → status: accepted, set approved_at. No payments yet.
    // SALE   → status: need_to_pay, start 60-day timer. No payments yet.

    public function adminApprove(Request $request, int $id): JsonResponse
    {
        $admin    = $request->user();
        $contract = Contract::findOrFail($id);

        $allowedStatuses = [
            Contract::STATUS_ADMIN_PENDING,
            Contract::STATUS_NAFATH_APPROVED,
        ];

        if (! in_array($contract->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'العقد ليس في انتظار المراجعة.',
            ], 422);
        }

        $now = now();

        if ($contract->type === Contract::TYPE_RENTAL) {
            // Rental: just accept, no payments/invoices yet
            $contract->update([
                'status'      => Contract::STATUS_APPROVED,
                'admin_id'    => $admin?->id,
                'approved_at' => $now,
            ]);

            Log::info('Rental contract accepted', ['contract_id' => $contract->id]);

        } else {
            // Sale: move to need_to_pay, start 60-day window
            $contract->update([
                'status'           => Contract::STATUS_NEED_TO_PAY,
                'admin_id'         => $admin?->id,
                'timer_started_at' => $now,
            ]);

            Log::info('Sale contract moved to need_to_pay', [
                'contract_id'   => $contract->id,
                'timer_started' => $now->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    // ── reject ────────────────────────────────────────────────────────────────

    public function reject(Request $request, int $id): JsonResponse
    {
        $admin    = $request->user();
        $contract = Contract::findOrFail($id);

        $rejectableStatuses = [
            Contract::STATUS_ADMIN_PENDING,
            Contract::STATUS_NAFATH_APPROVED,
        ];

        if (! in_array($contract->status, $rejectableStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفض العقد في حالته الحالية.',
            ], 422);
        }

        $contract->update([
            'status'      => Contract::STATUS_REJECTED,
            'admin_id'    => $admin?->id,
            'approved_at' => null,
        ]);

        $contract->payments()->delete();

        return response()->json([
            'success' => true,
            'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    // ── uploadSaleReceipt (USER) ──────────────────────────────────────────────
    //
    // POST /contracts/{id}/upload-sale-receipt
    // User uploads payment receipt → status: receipt_review
    // Can be called multiple times (no check on existing receipt).

    public function uploadSaleReceipt(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::findOrFail($id);

        if ($contract->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($contract->status !== Contract::STATUS_NEED_TO_PAY) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفع إيصال في هذه المرحلة. العقد يجب أن يكون في حالة "بانتظار الدفع".',
            ], 422);
        }

        // Check 60-day window hasn't expired
        if ($contract->paymentWindowExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'انتهت مدة الـ 60 يوماً للسداد. سيتم إغلاق العقد على المبلغ المدفوع.',
            ], 422);
        }



        $request->validate([
            'payment_receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        // Store new receipt (keep previous ones for audit — new path overwrites pointer)
        $path = $request->file('payment_receipt')->store('contracts/sale-receipts', 'public');

        $contract->update([
            'sale_receipt_path' => $path,
            'status'            => Contract::STATUS_RECEIPT_REVIEW,
        ]);

        Log::info('Sale contract receipt uploaded', [
            'contract_id' => $contract->id,
            'user_id'     => $user->id,
            'path'        => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الإيصال بنجاح. سيتم مراجعته من قِبل الإدارة.',
            'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    // ── reviewPayment (ADMIN) ─────────────────────────────────────────────────
    //
    // POST /admin/contracts/{id}/review-payment
    // Admin enters the amount the user actually transferred.
    // Logic:
    //   total_amount_paid += entered_amount
    //   if total_amount_paid >= 6600 OR 60d expired → closeContract()
    //   else → status back to need_to_pay

    public function reviewPayment(Request $request, int $id): JsonResponse
    {
        $admin    = $request->user();
        $contract = Contract::findOrFail($id);

        if ($contract->status !== Contract::STATUS_RECEIPT_REVIEW) {
            return response()->json([
                'success' => false,
                'message' => 'العقد ليس في حالة مراجعة الإيصال.',
            ], 422);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:1',
        ]);

        $amountPaid    = (float) $validated['amount_paid'];
        $newTotalPaid  = (float) $contract->total_amount_paid + $amountPaid;
        $newTotalPaid  = min($newTotalPaid, Contract::FULL_PRICE); // cap at full price

        // Update paid total
        $contract->update(['total_amount_paid' => $newTotalPaid]);

        Log::info('Admin reviewed sale payment', [
            'contract_id'     => $contract->id,
            'amount_entered'  => $amountPaid,
            'new_total_paid'  => $newTotalPaid,
            'admin_id'        => $admin?->id,
        ]);

        // ── Decision: close or wait ───────────────────────────────────────────

        $shouldClose = $contract->isFullyPaid() || $contract->paymentWindowExpired();

        if ($shouldClose) {
            $this->closeContract($contract->fresh(), $admin?->id);

            $contract->refresh();

            return response()->json([
                'success' => true,
                'message' => 'تم إغلاق العقد. سيتم تفعيل الاستثمار بعد 35 يوماً.',
                'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
            ]);
        }

        // Partial payment — back to need_to_pay, show countdown
        $contract->update(['status' => Contract::STATUS_NEED_TO_PAY]);

        $daysLeft = $contract->fresh()->paymentWindowDaysLeft();

        return response()->json([
            'success'         => true,
            'message'         => "تم تسجيل الدفعة. إجمالي المدفوع: {$newTotalPaid} ر.س. المتبقي للسداد: " . (Contract::FULL_PRICE - $newTotalPaid) . " ر.س. لديك {$daysLeft} يوماً متبقية.",
            'total_paid'      => $newTotalPaid,
            'remaining'       => Contract::FULL_PRICE - $newTotalPaid,
            'days_left'       => $daysLeft,
            'data'            => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    // ── updatePaymentReceipt (legacy — for non-sale contracts) ────────────────

    public function updatePaymentReceipt(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $contract = Contract::with('user')->findOrFail($id);

        if (! $this->isAdmin($user) && $contract->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'payment_receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $path = $validated['payment_receipt']->store('contracts/payment-receipts', 'public');

        Log::info('Payment receipt updated', ['contract_id' => $contract->id]);

        $contract->update(['payment_receipt_path' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Payment receipt updated.',
            'data'    => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    // ── payments (user: on-demand) ────────────────────────────────────────────

    public function payments(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        $acceptedStatuses = [Contract::STATUS_APPROVED, Contract::STATUS_ACCEPTED];

        if (! in_array($contract->status, $acceptedStatuses, true)) {
            return response()->json(['success' => false, 'message' => 'العقد غير مقبول بعد.'], 422);
        }

        if (! $contract->isActivated()) {
            return response()->json([
                'success'         => false,
                'message'         => 'لم يتم تفعيل الاستثمار بعد. سيُفعَّل بتاريخ ' . $contract->activationDate()?->format('Y-m-d'),
                'activation_date' => $contract->activationDate()?->toIso8601String(),
            ], 422);
        }

        $payments = $contract->payments()->get()->map->toApiArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'contract_id'     => $contract->id,
                'activation_date' => $contract->activationDate()?->toIso8601String(),
                'payments'        => $payments,
            ],
        ]);
    }

    // ── Private: closeContract ────────────────────────────────────────────────
    //
    // Called when:
    //  a) user paid full 6600
    //  b) admin reviews partial and 60d already expired
    //  c) Artisan command detects 60d expiry
    //
    // Sets status = accepted, calculates monthly rent from paid amount,
    // generates 12 payment rows, creates year-1 maintenance invoice.

    public function closeContract(Contract $contract, ?int $adminId = null): void
    {
        if (in_array($contract->status, [Contract::STATUS_ACCEPTED], true)) {
            Log::info('closeContract: already closed', ['contract_id' => $contract->id]);
            return; // already closed
        }

        DB::transaction(function () use ($contract, $adminId) {
            $closedAt       = now();
            $monthlyRent    = $contract->calculateMonthlyRent();

            // Floor rent: if nothing paid somehow, use 0
            if ($monthlyRent <= 0) {
                Log::warning('closeContract: monthly rent is 0', ['contract_id' => $contract->id]);
            }
            $contract->update([
                'status'                 => Contract::STATUS_ACCEPTED,
                'admin_id'               => $adminId ?? $contract->admin_id,
                'approved_at'            => $closedAt,          // activation countdown starts here
                'total_amount'           => Contract::FULL_PRICE,
                'monthly_payment_amount' => $monthlyRent,
            ]);

            if ($contract->type !== Contract::TYPE_SALE) {
                Log::info('closeContract: skip rental sync (not a sale contract)', ['contract_id' => $contract->id]);

                return;
            }

            // Latest rental for this user (same flow as the sale), must already be approved
            $rentalContract = Contract::query()
                ->where('user_id', $contract->user_id)
                ->where('type', Contract::TYPE_RENTAL)
                ->where('status', Contract::STATUS_APPROVED)
                ->orderByDesc('id')
                ->first();

            if (! $rentalContract) {
                Log::info('closeContract: no approved rental contract to link', [
                    'sale_contract_id' => $contract->id,
                    'user_id'          => $contract->user_id,
                ]);

                return;
            }

            $rentalContract->update([
                'total_amount'           => $contract->total_amount_paid,
                'monthly_payment_amount' => $contract->monthly_payment_amount,
            ]);

            $this->generatePayments($rentalContract, $closedAt);

            InvoiceController::createFirstYear($rentalContract, $closedAt);

            Log::info('Sale contract closed', [
                'contract_id'    => $contract->id,
                'monthly_rent'   => $monthlyRent,
                'total_paid'     => $contract->total_amount_paid,
                'activation_due' => $closedAt->copy()->addDays(Contract::ACTIVATION_DAYS)->toDateString(),
            ]);
        });
    }

    // ── Private: generatePayments ─────────────────────────────────────────────

    private function generatePayments(Contract $contract, \Carbon\Carbon $closedAt): void
    {
        if ($contract->payments()->exists()) return;

        $monthlyAmount = (float) $contract->monthly_payment_amount;

        if ($monthlyAmount <= 0) {
            Log::warning('generatePayments: amount is 0', ['contract_id' => $contract->id]);
            return;
        }

        $activationDate = $closedAt->copy()->addDays(Contract::ACTIVATION_DAYS);

        $rows = [];
        for ($i = 1; $i <= Contract::PAYMENT_MONTHS; $i++) {
            $dueDate = $activationDate->copy()->addMonths($i)->startOfMonth();
            $rows[]  = [
                'contract_id'  => $contract->id,
                'month_number' => $i,
                'amount'       => $monthlyAmount,
                'due_date'     => $dueDate->toDateString(),
                'payment_date' => null,
                'status'       => Payment::STATUS_PENDING,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        Payment::insert($rows);
    }

    protected function isAdmin(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    protected function toApi(Contract $contract): array
    {
        $daysLeft = $contract->type === Contract::TYPE_SALE
            ? $contract->paymentWindowDaysLeft()
            : null;

        return [
            'id'                     => $contract->id,
            'user_id'                => $contract->user_id,
            'user'                   => $contract->user?->toApiArray(),
            'type'                   => $contract->type,
            'title'                  => $contract->title,
            'file_path'              => $contract->file_path,
            'file_url'               => $contract->file_path
                                            ? Storage::disk('public')->url($contract->file_path)
                                            : null,
            'payment_receipt_path'   => $contract->payment_receipt_path,
            'payment_receipt_url'    => $contract->payment_receipt_path
                                            ? Storage::disk('public')->url($contract->payment_receipt_path)
                                            : null,
            'sale_receipt_path'      => $contract->sale_receipt_path,
            'sale_receipt_url'       => $contract->sale_receipt_path
                                            ? Storage::disk('public')->url($contract->sale_receipt_path)
                                            : null,
            'status'                 => $contract->status,
            'nafath_reference'       => $contract->nafath_reference,
            'admin_id'               => $contract->admin_id,
            'total_amount'           => $contract->total_amount ? (float) $contract->total_amount : null,
            'total_amount_paid'      => (float) $contract->total_amount_paid,
            'monthly_payment_amount' => $contract->monthly_payment_amount ? (float) $contract->monthly_payment_amount : null,
            'timer_started_at'       => optional($contract->timer_started_at)?->toIso8601String(),
            'payment_window_days_left' => $daysLeft,
            'payment_window_expired' => $contract->type === Contract::TYPE_SALE
                                            ? $contract->paymentWindowExpired()
                                            : null,
            'full_price'             => Contract::FULL_PRICE,
            'activation_date'        => $contract->activationDate()?->toIso8601String(),
            'is_activated'           => $contract->isActivated(),
            'approved_at'            => optional($contract->approved_at)?->toIso8601String(),
            'created_at'             => optional($contract->created_at)?->toIso8601String(),
            'updated_at'             => optional($contract->updated_at)?->toIso8601String(),
        ];
    }
}