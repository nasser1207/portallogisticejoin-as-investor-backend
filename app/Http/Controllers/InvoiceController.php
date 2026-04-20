<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    // ── USER: get my invoices ─────────────────────────────────────────────────
    //
    // GET /portallogistice/invoices
    // GET /portallogistice/invoices?contract_id=X&status=pending&year=1

    public function userIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::with('contract')
            ->where('user_id', $user->id)
            ->orderBy('due_date')
            ->orderBy('year');

        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }
        if ($c = (int) $request->query('contract_id', 0)) {
            $query->where('contract_id', $c);
        }
        if ($y = (int) $request->query('year', 0)) {
            $query->where('year', $y);
        }

        $invoices = $query->get();

        // Group by contract for better frontend rendering
        $grouped = $invoices->groupBy('contract_id')->map(function ($items) {
            $contract = $items->first()->contract;
            return [
                'contract_id'     => $contract->id,
                'contract_title'  => $contract->title,
                'contract_type'   => $contract->type,
                'is_activated'    => $contract->isActivated(),
                'activation_date' => $contract->activationDate()?->toIso8601String(),
                'invoices'        => $items->map->toApiArray()->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'contracts' => $grouped,
                'total'     => $invoices->count(),
                'summary'   => [
                    'pending'       => $invoices->where('status', Invoice::STATUS_PENDING)->count(),
                    'admin_pending' => $invoices->where('status', Invoice::STATUS_ADMIN_PENDING)->count(),
                    'approved'      => $invoices->where('status', Invoice::STATUS_APPROVED)->count(),
                    'rejected'      => $invoices->where('status', Invoice::STATUS_REJECTED)->count(),
                ],
            ],
        ]);
    }

    // ── USER: upload receipt for an invoice ───────────────────────────────────
    //
    // POST /portallogistice/invoices/{id}/receipt
    // Body: multipart  →  receipt: file

    public function uploadReceipt(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $invoice = Invoice::with('contract')->where('user_id', $user->id)->findOrFail($id);

        // Only pending invoices can receive a receipt upload
        if ($invoice->status !== Invoice::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفع إيصال لهذه الفاتورة في حالتها الحالية.',
            ], 422);
        }

        // Contract must be activated before user can pay
        if (! $invoice->contract->isActivated()) {
            $activationDate = $invoice->contract->activationDate()?->format('Y-m-d');
            return response()->json([
                'success'         => false,
                'message'         => "لم يتم تفعيل العقد بعد. سيتم التفعيل بتاريخ {$activationDate}.",
                'activation_date' => $activationDate,
            ], 422);
        }

        $request->validate([
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        // Delete old receipt if one was previously uploaded
        if ($invoice->receipt_path && Storage::disk('public')->exists($invoice->receipt_path)) {
            Storage::disk('public')->delete($invoice->receipt_path);
        }

        $path = $request->file('receipt')->store('invoices/receipts', 'public');

        $invoice->update([
            'receipt_path' => $path,
            'status'       => Invoice::STATUS_ADMIN_PENDING,
        ]);

        Log::info('Invoice receipt uploaded by user', [
            'invoice_id'  => $invoice->id,
            'contract_id' => $invoice->contract_id,
            'user_id'     => $user->id,
            'year'        => $invoice->year,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الإيصال بنجاح. سيتم مراجعته من قِبل الإدارة.',
            'data'    => $invoice->fresh('contract')->toApiArray(),
        ]);
    }

    // ── ADMIN: list all invoices with filters ─────────────────────────────────
    //
    // GET /admin/invoices
    // Params: status, year, contract_id, user_id, due_from, due_to, per_page

    public function adminIndex(Request $request): JsonResponse
    {
        $query = Invoice::with(['contract', 'user'])
            ->orderBy('due_date')
            ->orderBy('id');

        if ($s = $request->query('status')) {
            $query->where('status', $s);
        }
        if ($y = (int) $request->query('year', 0)) {
            $query->where('year', $y);
        }
        if ($c = (int) $request->query('contract_id', 0)) {
            $query->where('contract_id', $c);
        }
        if ($u = (int) $request->query('user_id', 0)) {
            $query->where('user_id', $u);
        }
        if ($from = $request->query('due_from')) {
            $query->whereDate('due_date', '>=', $from);
        }
        if ($to = $request->query('due_to')) {
            $query->whereDate('due_date', '<=', $to);
        }

        $perPage   = min((int) $request->query('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'invoices'   => $paginated->map(fn (Invoice $i) => $this->toApiWithRelations($i)),
                'pagination' => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ── ADMIN: approve invoice ────────────────────────────────────────────────
    //
    // POST /admin/invoices/{id}/approve

    public function approve(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with('contract')->findOrFail($id);

        if ($invoice->status !== Invoice::STATUS_ADMIN_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'الفاتورة ليست في انتظار المراجعة.',
            ], 422);
        }

        $invoice->update([
            'status'      => Invoice::STATUS_APPROVED,
            'admin_notes' => $request->input('notes'),
        ]);

        Log::info('Invoice approved by admin', [
            'invoice_id' => $invoice->id,
            'admin_id'   => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الفاتورة.',
            'data'    => $this->toApiWithRelations($invoice->fresh(['contract', 'user'])),
        ]);
    }

    // ── ADMIN: reject invoice → create new pending invoice ───────────────────
    //
    // POST /admin/invoices/{id}/reject
    // Body: notes (optional string)

    public function reject(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with(['contract', 'user'])->findOrFail($id);

        if ($invoice->status !== Invoice::STATUS_ADMIN_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'الفاتورة ليست في انتظار المراجعة.',
            ], 422);
        }

        $notes = $request->input('notes', 'تم رفض الإيصال. يرجى إعادة الرفع.');

        DB::transaction(function () use ($invoice, $notes, &$newInvoice) {
            // Mark original as rejected
            $invoice->update([
                'status'      => Invoice::STATUS_REJECTED,
                'admin_notes' => $notes,
            ]);

            // Re-issue a fresh invoice so user can upload again
            $newInvoice = Invoice::create([
                'contract_id'       => $invoice->contract_id,
                'user_id'           => $invoice->user_id,
                'year'              => $invoice->year,
                'amount'            => $invoice->amount,
                'monthly_amount'    => $invoice->monthly_amount,
                'due_date'          => $invoice->due_date,
                'receipt_path'      => null,
                'status'            => Invoice::STATUS_PENDING,
                'admin_notes'       => null,
                'parent_invoice_id' => $invoice->id,
            ]);
        });

        Log::info('Invoice rejected, new invoice created', [
            'rejected_invoice_id' => $invoice->id,
            'new_invoice_id'      => $newInvoice->id,
            'admin_id'            => $request->user()?->id,
            'notes'               => $notes,
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'تم رفض الفاتورة وإنشاء فاتورة جديدة للمستثمر.',
            'data'        => [
                'rejected_invoice' => $this->toApiWithRelations($invoice->fresh(['contract', 'user'])),
                'new_invoice'      => $this->toApiWithRelations($newInvoice->load(['contract', 'user'])),
            ],
        ]);
    }

    // ── Called from ContractController after generatePayments ─────────────────

    /**
     * Create Year 1 maintenance invoice for a rental contract.
     * Call this right after generatePayments() in ContractController::send() / adminApprove().
     */
    public static function createFirstYear(Contract $contract, \Carbon\Carbon $approvedAt): void
    {
        // Only rental contracts have maintenance invoices
        if ($contract->type !== Contract::TYPE_RENTAL) return;

        // Avoid duplicates
        if ($contract->invoices()->where('year', 1)->exists()) return;

        $activationDate = $approvedAt->copy()->addDays(Contract::ACTIVATION_DAYS);
        $config         = Invoice::YEARS[1];

        Invoice::create([
            'contract_id'    => $contract->id,
            'user_id'        => $contract->user_id,
            'year'           => 1,
            'amount'         => $config['amount'],
            'monthly_amount' => $config['monthly'],
            // Due at end of year 1 (activation + 12 months)
           'due_date'       => $activationDate->copy()->addMonths(12)->toDateString(),
            'status'         => Invoice::STATUS_PENDING,
        ]);

        Log::info('Invoice year 1 created', ['contract_id' => $contract->id]);
    }

    /**
     * Create Year 2 maintenance invoice.
     * Can be called manually or via a scheduled command after year 1 ends.
     */
    public static function createSecondYear(Contract $contract): void
    {
        if ($contract->type !== Contract::TYPE_RENTAL) return;
        if ($contract->invoices()->where('year', 2)->exists()) return;

        $activationDate = $contract->activationDate();
        if (! $activationDate) return;

        $config = Invoice::YEARS[2];

        Invoice::create([
            'contract_id'    => $contract->id,
            'user_id'        => $contract->user_id,
            'year'           => 2,
            'amount'         => $config['amount'],
            'monthly_amount' => $config['monthly'],
            'due_date'       => $activationDate->copy()->addMonths(24)->toDateString(),
            'status'         => Invoice::STATUS_PENDING,
        ]);

        Log::info('Invoice year 2 created', ['contract_id' => $contract->id]);
    }

    /**
     * Create Year 3 maintenance invoice.
     */
    public static function createThirdYear(Contract $contract): void
    {
        if ($contract->type !== Contract::TYPE_RENTAL) return;
        if ($contract->invoices()->where('year', 3)->exists()) return;

        $activationDate = $contract->activationDate();
        if (! $activationDate) return;

        $config = Invoice::YEARS[3];

        Invoice::create([
            'contract_id'    => $contract->id,
            'user_id'        => $contract->user_id,
            'year'           => 3,
            'amount'         => $config['amount'],
            'monthly_amount' => $config['monthly'],
            'due_date'       => $activationDate->copy()->addMonths(36)->toDateString(),
            'status'         => Invoice::STATUS_PENDING,
        ]);

        Log::info('Invoice year 3 created', ['contract_id' => $contract->id]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function toApiWithRelations(Invoice $invoice): array
    {
        $base     = $invoice->toApiArray();
        $contract = $invoice->contract;
        $user     = $invoice->user;

        $base['contract'] = $contract ? [
            'id'    => $contract->id,
            'title' => $contract->title,
            'type'  => $contract->type,
        ] : null;

        $base['user'] = $user ? [
            'id'          => $user->id,
            'name'        => $user->name,
            'national_id' => $user->national_id,
            'phone'       => $user->phone,
        ] : null;

        return $base;
    }
}
