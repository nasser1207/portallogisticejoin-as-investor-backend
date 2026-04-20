<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\InvestorRequest;
use App\Services\NafathSigningPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvestorRequestController extends Controller
{
    public function __construct(
        private readonly NafathSigningPipeline $pipeline
    ) {}

    // ── USER: submit request ──────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type'        => 'required|in:renew_contract,sell_bike,add_bike',
            'full_name'   => 'required|string|max:255',
            'national_id' => 'required|string|max:20',
            'phone'       => 'required|string|max:20',
            'contract_id' => 'nullable|integer|exists:contracts,id',
        ]);

        if (! empty($validated['contract_id'])) {
            $contract = Contract::where('id', $validated['contract_id'])
                ->where('user_id', $user->id)->first();
            if (! $contract) {
                return response()->json(['success' => false, 'message' => 'العقد المحدد غير موجود أو لا ينتمي لحسابك.'], 422);
            }
        }

        $req = InvestorRequest::create([
            'user_id'     => $user->id,
            'type'        => $validated['type'],
            'status'      => InvestorRequest::STATUS_PENDING,
            'full_name'   => $validated['full_name'],
            'national_id' => $validated['national_id'],
            'phone'       => $validated['phone'],
            'contract_id' => $validated['contract_id'] ?? null,
        ]);

        Log::info('Investor request submitted', [
            'request_id' => $req->id,
            'user_id'    => $user->id,
            'type'       => $req->type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلبك بنجاح. سيتواصل معك فريقنا قريباً.',
            'data'    => $req->toApiArray(),
        ], 201);
    }

    // ── USER: my requests ─────────────────────────────────────────────────────

    public function userIndex(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = InvestorRequest::where('user_id', $user->id)->latest('id');

        if ($t = $request->query('type'))   $query->where('type', $t);
        if ($s = $request->query('status')) $query->where('status', $s);

        $requests = $query->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'requests' => $requests->map->toApiArray()->values(),
                'summary'  => [
                    'total'    => $requests->count(),
                    'pending'  => $requests->whereIn('status', [
                        InvestorRequest::STATUS_PENDING,
                        InvestorRequest::STATUS_IN_REVIEW,
                    ])->count(),
                    'approved' => $requests->where('status', InvestorRequest::STATUS_APPROVED)->count(),
                    'rejected' => $requests->where('status', InvestorRequest::STATUS_REJECTED)->count(),
                ],
            ],
        ]);
    }

    // ── USER: initiate Nafath for invoice signing ─────────────────────────────

    /**
     * Called when the user taps "Sign Invoice" in the app.
     * Works exactly like ContractController@nafath but for investor request invoices.
     */
    public function nafath(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $req  = InvestorRequest::findOrFail($id);

        // Ownership check
        if ($req->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Must have an invoice deployed by admin
        if (empty($req->admin_invoice_path)) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد فاتورة لتوقيعها بعد.',
            ], 422);
        }

        // Only allow initiating when in the right statuses
        if (! in_array($req->status, [
            InvestorRequest::STATUS_INVOICE_SENT,
            InvestorRequest::STATUS_NAFATH_PENDING,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن بدء التوثيق في الحالة الحالية للطلب.',
            ], 422);
        }

        $result = $this->pipeline->initiate($req);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'فشل بدء التوثيق عبر نفاذ.',
                'sadq'    => $result,
            ], 422);
        }

        return response()->json([
            'success'          => true,
            'message'          => 'تم إرسال الطلب إلى تطبيق نفاذ 📱 يرجى فتح التطبيق واختيار الرقم للموافقة',
            'challenge_number' => $result['challenge_number'] ?? null,
            'data'             => $req->fresh()->toApiArray(),
        ]);
    }

    // ── ADMIN: all requests ───────────────────────────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        $query = InvestorRequest::with(['user', 'contract'])->latest('id');

        if ($t = $request->query('type'))              $query->where('type', $t);
        if ($s = $request->query('status'))            $query->where('status', $s);
        if ($u = (int) $request->query('user_id', 0)) $query->where('user_id', $u);

        $perPage   = min((int) $request->query('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'requests'   => $paginated->map(fn (InvestorRequest $r) => $this->toApiWithUser($r)),
                'pagination' => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ── ADMIN: approve ────────────────────────────────────────────────────────

    public function approve(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $req   = InvestorRequest::findOrFail($id);

    

        $req->update([
            'status'      => InvestorRequest::STATUS_APPROVED,
            'admin_notes' => $request->input('notes'),
            'actioned_by' => $admin->id,
            'actioned_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الطلب.',
            'data'    => $this->toApiWithUser($req->fresh(['user', 'contract'])),
        ]);
    }

    // ── ADMIN: reject ─────────────────────────────────────────────────────────

    public function reject(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $req   = InvestorRequest::findOrFail($id);

    
        $req->update([
            'status'      => InvestorRequest::STATUS_REJECTED,
            'admin_notes' => $request->input('notes', 'تم رفض الطلب.'),
            'actioned_by' => $admin->id,
            'actioned_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب.',
            'data'    => $this->toApiWithUser($req->fresh(['user', 'contract'])),
        ]);
    }

    // ── ADMIN: send WhatsApp ──────────────────────────────────────────────────

    public function sendWhatsapp(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $req   = InvestorRequest::findOrFail($id);

        if ($req->type !== InvestorRequest::TYPE_ADD_BIKE) {
            return response()->json(['success' => false, 'message' => 'هذا الإجراء مخصص لطلبات إضافة الدراجة فقط.'], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:4096',
        ], [
            'message.required' => 'نص الرسالة مطلوب.',
            'message.max'      => 'الرسالة طويلة جداً (الحد الأقصى 4096 حرفاً).',
        ]);

        $sent = $this->dispatchWhatsapp($req->phone, $validated['message']);

        $req->update([
            'status'      => InvestorRequest::STATUS_WHATSAPP_SENT,
            'admin_notes' => $validated['message'],
            'actioned_by' => $admin->id,
            'actioned_at' => now(),
        ]);

        Log::info('WhatsApp sent for add_bike request', [
            'request_id' => $req->id,
            'phone'      => $req->phone,
            'sent'       => $sent,
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'تم إرسال رسالة الواتساب.',
            'whatsapp_text' => $validated['message'],
            'phone'         => $req->phone,
            'api_sent'      => $sent,
            'data'          => $this->toApiWithUser($req->fresh(['user', 'contract'])),
        ]);
    }

    // ── ADMIN: deploy invoice ─────────────────────────────────────────────────

    /**
     * Admin uploads an invoice → status becomes invoice_sent so user can sign it via Nafath.
     */
    public function deployContract(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $req   = InvestorRequest::findOrFail($id);

        if ($req->status === InvestorRequest::STATUS_REJECTED) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إنشاء عقد لطلب مرفوض.'], 422);
        }

        $request->validate([
            'invoice' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
        ]);

        // Clean up old invoice if it exists
        if ($req->admin_invoice_path && Storage::disk('public')->exists($req->admin_invoice_path)) {
            Storage::disk('public')->delete($req->admin_invoice_path);
        }

        $invoicePath = $request->file('invoice')->store('invoices/admin-deploy', 'public');

        $req->update([
            'admin_invoice_path' => $invoicePath,
            'status'             => InvestorRequest::STATUS_INVOICE_SENT, // ← user can now see + sign
            'actioned_by'        => $admin->id,
            'actioned_at'        => now(),
        ]);

        Log::info('Admin deployed invoice for investor request', [
            'request_id'   => $req->id,
            'invoice_path' => $invoicePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرفاق الفاتورة بالطلب. بإمكان المستخدم الآن توقيعها عبر نفاذ.',
            'data'    => $this->toApiWithUser($req->fresh(['user', 'contract'])),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function dispatchWhatsapp(string $phone, string $message): bool
    {
        $apiUrl   = config('services.whatsapp.url');
        $apiToken = config('services.whatsapp.token');

        if (! $apiUrl || ! $apiToken) {
            Log::warning('WhatsApp not configured.');
            return false;
        }

        try {
            return Http::withToken($apiToken)
                ->timeout(10)
                ->post($apiUrl, ['phone' => $phone, 'message' => $message])
                ->successful();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp dispatch failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function toApiWithUser(InvestorRequest $r): array
    {
        $base = $r->toApiArray();
        $base['user']     = $r->user
            ? ['id' => $r->user->id, 'name' => $r->user->name, 'national_id' => $r->user->national_id, 'phone' => $r->user->phone]
            : null;
        $base['contract'] = $r->contract
            ? ['id' => $r->contract->id, 'title' => $r->contract->title, 'type' => $r->contract->type, 'status' => $r->contract->status]
            : null;
        return $base;
    }
}
