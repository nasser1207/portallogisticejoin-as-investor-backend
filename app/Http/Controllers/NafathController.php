<?php

namespace App\Http\Controllers;

use App\Services\SadqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Proxies Nafath flows to Sadq Tasheel API for the React app
 * (ContractForm / legacy flows under /api/portallogistice/nafath/*).
 */
class NafathController extends Controller
{
    public function __construct(
        protected SadqService $sadq
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'national_id' => 'required|string|max:32',
            'contract_type' => 'required|string|in:selling,rental',
        ]);

        $user = $request->user();
        if (! $this->nationalIdAllowedForUser($user, $validated['national_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'National ID does not match authenticated user.',
            ], 403);
        }

        try {
            $result = $this->sadq->initiateNafath($validated['national_id'], $validated['contract_type']);
        } catch (\Throwable $e) {
            Log::error('Nafath initiate: Sadq exception', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json($this->frontendErrorPayload($e->getMessage()));
        }

        if (! $result['success'] || (int) ($result['http_status'] ?? 0) !== 200) {
            Log::warning('Nafath initiate: Sadq reported failure', [
                'message' => $result['message'] ?? null,
                'http_status' => $result['http_status'] ?? null,
            ]);

            return response()->json($this->frontendErrorPayload($result['message'] ?? 'Sadq initiate failed.'));
        }

        $sadq = $result['sadq_response'] ?? [];
        if ($this->sadqResponseImpliesApproved($sadq)) {
            return response()->json([
                'status' => 'approved',
                'request_id' => $result['request_id'],
                'message' => data_get($sadq, 'message') ?? 'Identity already verified.',
            ]);
        }

        $random = $this->extractNafathRandom($sadq);
        if ($random === null || $random === '') {
            $random = (string) random_int(100000, 999999);
            Log::info('Nafath initiate: no code from Sadq response, using UI placeholder');
        }

        return response()->json([
            'status' => 'sent',
            'request_id' => $result['request_id'],
            'external_response' => [[
                'random' => $random,
                'error' => 'Success',
                'status' => 'pending',
            ]],
            'message' => $result['message'] ?? data_get($sadq, 'message'),
        ]);
    }

    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'national_id' => 'required|string|max:32',
            'contract_type' => 'required|string|in:selling,rental',
        ]);

        $user = $request->user();
        if (! $this->nationalIdAllowedForUser($user, $validated['national_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'National ID does not match authenticated user.',
            ], 403);
        }

        try {
            $out = $this->sadq->getLocalStatus($validated['national_id'], $validated['contract_type']);
        } catch (\Throwable $e) {
            Log::error('Nafath checkStatus: Sadq exception', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'status' => 'error',
                'code' => '500-999-999',
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ]);
        }

        if (! $out['success']) {
            return response()->json([
                'status' => 'error',
                'code' => '500-999-999',
                'message' => $out['message'] ?? 'Sadq status check failed.',
                'error' => $out['message'] ?? 'Sadq status check failed.',
                'request_id' => $out['request_id'] ?? null,
            ]);
        }

        $st = $out['status'];

        if ($st === 'error') {
            return response()->json([
                'status' => 'error',
                'code' => '500-999-999',
                'message' => $out['message'] ?? 'Nafath request failed on Sadq side.',
                'error' => $out['message'] ?? 'error',
                'request_id' => $out['request_id'] ?? null,
            ]);
        }

        $externalStatus = match ($st) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'not_found' => 'not_found',
            default => 'pending',
        };

        return response()->json([
            'status' => $st,
            'request_id' => $out['request_id'] ?? null,
            'external_response' => [[
                'error' => 'Success',
                'status' => $externalStatus,
            ]],
            'message' => $out['message'] ?? null,
            'approved_at' => $st === 'approved' ? now()->toIso8601String() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $sadq
     */
    protected function sadqResponseImpliesApproved(array $sadq): bool
    {
        $status = strtolower((string) data_get(
            $sadq,
            'status',
            data_get($sadq, 'data.status', data_get($sadq, '0.status', ''))
        ));
        $error = strtolower((string) data_get($sadq, '0.error', data_get($sadq, 'error', '')));

        return in_array($status, ['approved', 'completed', 'complete', 'success', 'verified'], true)
            || $error === 'approved';
    }

    /**
     * @param  array<string, mixed>  $sadq
     */
    protected function extractNafathRandom(array $sadq): ?string
    {
        $v = data_get($sadq, 'random')
            ?? data_get($sadq, 'code')
            ?? data_get($sadq, 'nafathCode')
            ?? data_get($sadq, 'verificationCode')
            ?? data_get($sadq, 'data.random')
            ?? data_get($sadq, '0.random')
            ?? data_get($sadq, '0.code');

        if ($v === null || $v === '') {
            return null;
        }

        return is_scalar($v) ? (string) $v : null;
    }

    /**
     * @param  \App\Models\User|null  $user
     */
    protected function nationalIdAllowedForUser($user, string $nationalId): bool
    {
        if (! $user) {
            return false;
        }

        $stored = $user->national_id ?? null;
        if ($stored === null || $stored === '') {
            return true;
        }

        return $this->normalizeNationalId((string) $stored) === $this->normalizeNationalId($nationalId);
    }

    protected function normalizeNationalId(string $id): string
    {
        return preg_replace('/\D/', '', $id) ?? '';
    }

    /**
     * Shape expected by ContractForm.js on initiate (HTTP 200 + status error).
     *
     * @return array<string, mixed>
     */
    protected function frontendErrorPayload(string $message): array
    {
        return [
            'status' => 'error',
            'code' => '500-999-999',
            'message' => $message,
            'external_response' => [[
                'error' => $message,
                'status' => 'error',
            ]],
        ];
    }
}
