<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Services\SadqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function __construct(
        protected SadqService $sadqService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Contract::with(['user', 'admin'])->latest('id');

        if (! $this->isAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');
        $userId = (int) $request->query('user_id', 0);

        if ($status !== '') {
            if ($status === Contract::STATUS_ADMIN_PENDING) {
                $query->whereIn('status', [Contract::STATUS_ADMIN_PENDING, Contract::STATUS_NAFATH_APPROVED]);
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
            'data' => $query->get()->map(fn (Contract $contract) => $this->toApi($contract)),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $contract = Contract::with(['user', 'admin'])->findOrFail($id);

        if (! $this->isAdmin($user) && $contract->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->toApi($contract),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'national_id' => 'nullable|string|max:32',
            'type' => 'required|in:sale,rental',
            'title' => 'required|string|max:255',
            'file' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        $targetUser = null;
        if (! empty($validated['user_id'])) {
            $targetUser = User::find($validated['user_id']);
        } elseif (! empty($validated['national_id'])) {
            $targetUser = User::where('national_id', $validated['national_id'])->first();
        }

        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Assigned user not found.',
            ], 422);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('contracts', 'public');
        }

        $contract = Contract::create([
            'user_id' => $targetUser->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'file_path' => $filePath,
            'status' => Contract::STATUS_DRAFT,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->toApi($contract->fresh(['user', 'admin'])),
        ], 201);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        if (! in_array($contract->status, [Contract::STATUS_DRAFT, Contract::STATUS_REJECTED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Contract can be sent only from draft/rejected state.',
            ], 422);
        }

        $contract->update(['status' => Contract::STATUS_SENT]);

        return response()->json([
            'success' => true,
            'data' => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    public function nafath(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $contract = Contract::with('user')->findOrFail($id);

        if ($contract->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if (! in_array($contract->status, [Contract::STATUS_SENT, Contract::STATUS_NAFATH_PENDING], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Nafath can be initiated only for sent contracts.',
            ], 422);
        }

        if ((string) ($contract->user?->national_id ?? '') === '') {
            return response()->json([
                'success' => false,
                'message' => 'User national ID is required for Nafath.',
            ], 422);
        }

        Log::info('Contract Nafath request started', [
            'contract_id' => $contract->id,
            'user_id' => $contract->user_id,
            'from_status' => $contract->status,
        ]);

        $result = $this->sadqService->initiateNafath($contract, $contract->type);

        if (! ($result['success'] ?? false)) {
            Log::warning('Contract Nafath request failed', [
                'contract_id' => $contract->id,
                'response' => $result,
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Nafath initiation failed.',
                'sadq' => $result,
            ], 422);
        }

        $contract->refresh();

        Log::info('Contract Nafath request sent', [
            'contract_id' => $contract->id,
            'nafath_reference' => $contract->nafath_reference,
            'new_status' => $contract->status,
            'challenge_number' => $result['challenge_number'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الطلب إلى تطبيق نفاذ 📱 يرجى فتح التطبيق واختيار الرقم للموافقة',
            'challenge_number' => $result['challenge_number'] ?? null,
            'data' => $this->toApi($contract->fresh(['user', 'admin'])),
            'sadq' => $result,
        ]);
    }

    public function adminApprove(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $contract = Contract::findOrFail($id);

        if (! in_array($contract->status, [Contract::STATUS_ADMIN_PENDING, Contract::STATUS_NAFATH_APPROVED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Contract is not waiting for admin review.',
            ], 422);
        }

        $contract->update([
            'status' => Contract::STATUS_APPROVED,
            'admin_id' => $admin?->id,
            'approved_at' => now(),
        ]);

        $autoCreatedRental = null;
        if ($contract->type === Contract::TYPE_SALE) {
            $rentalTitle = 'Rental Contract - Sale #'.$contract->id;
            $alreadyExists = Contract::where('user_id', $contract->user_id)
                ->where('type', Contract::TYPE_RENTAL)
                ->where('title', $rentalTitle)
                ->exists();

            if (! $alreadyExists) {
                $autoCreatedRental = Contract::create([
                    'user_id' => $contract->user_id,
                    'type' => Contract::TYPE_RENTAL,
                    'title' => $rentalTitle,
                    'status' => Contract::STATUS_SENT,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->toApi($contract->fresh(['user', 'admin'])),
            'auto_created_rental' => $autoCreatedRental ? $this->toApi($autoCreatedRental->fresh(['user', 'admin'])) : null,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $contract = Contract::findOrFail($id);

        if (! in_array($contract->status, [Contract::STATUS_ADMIN_PENDING, Contract::STATUS_NAFATH_APPROVED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Contract is not waiting for admin review.',
            ], 422);
        }

        $contract->update([
            'status' => Contract::STATUS_REJECTED,
            'admin_id' => $admin?->id,
            'approved_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->toApi($contract->fresh(['user', 'admin'])),
        ]);
    }

    protected function isAdmin(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    protected function toApi(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'user_id' => $contract->user_id,
            'user' => $contract->user?->toApiArray(),
            'type' => $contract->type,
            'title' => $contract->title,
            'file_path' => $contract->file_path,
            'file_url' => $contract->file_path ? Storage::disk('public')->url($contract->file_path) : null,
            'status' => $contract->status,
            'nafath_reference' => $contract->nafath_reference,
            'admin_id' => $contract->admin_id,
            'approved_at' => optional($contract->approved_at)?->toIso8601String(),
            'created_at' => optional($contract->created_at)?->toIso8601String(),
            'updated_at' => optional($contract->updated_at)?->toIso8601String(),
        ];
    }
}
