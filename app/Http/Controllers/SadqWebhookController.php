<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\SadqNafathRequest;
use App\Services\SadqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SadqWebhookController extends Controller
{
    public function __construct(
        protected SadqService $sadqService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload   = $request->all();
        $sourceIp  = (string) $request->ip();

        Log::info('Sadq webhook received', [
            'ip'      => $sourceIp,
            'payload' => $payload,
        ]);

        if (! $this->isAuthorizedWebhook($request)) {
            Log::warning('Sadq webhook unauthorized source', ['ip' => $sourceIp]);
            return response()->json(['success' => false, 'message' => 'Unauthorized webhook source.'], 401);
        }

        $requestId = $this->extractRequestId($payload);
        $status    = $this->normalizeStatus($payload);

        Log::info('Sadq webhook parsed', [
            'request_id' => $requestId,
            'status'     => $status,
            'raw_status' => data_get($payload, 'Status', data_get($payload, 'status')),
        ]);

        if ($requestId === null || $requestId === '') {
            Log::warning('Sadq webhook: missing requestId', ['keys' => array_keys($payload)]);
            return response()->json(['success' => true]);
        }

        // Update or create the Nafath request record
        $record = SadqNafathRequest::where('request_id', $requestId)->first();
        if ($record) {
            $record->update([
                'status'       => $status,
                'last_payload' => array_merge($record->last_payload ?? [], [
                    'webhook'             => $payload,
                    'webhook_received_at' => now()->toIso8601String(),
                ]),
            ]);
        } else {
            SadqNafathRequest::create([
                'request_id'    => $requestId,
                'national_id'   => $this->extractNationalId($payload) ?? 'unknown',
                'contract_type' => null,
                'status'        => $status,
                'last_payload'  => [
                    'webhook'             => $payload,
                    'webhook_received_at' => now()->toIso8601String(),
                    'orphan'              => true,
                ],
            ]);
        }

        // Find matching contract
        $contract = Contract::where('nafath_reference', $requestId)->first();

        if (! $contract) {
            Log::warning('Sadq webhook: contract not found', ['request_id' => $requestId]);
            return response()->json(['success' => true]);
        }

        Log::info('Sadq webhook contract matched', [
            'contract_id'    => $contract->id,
            'current_status' => $contract->status,
        ]);

        $fromStatus = $contract->status;

        if ($status === SadqNafathRequest::STATUS_APPROVED) {

            $contract->update(['status' => 'nafath_approved']);

            // Try to sign the document if a file exists
           if (! empty($contract->file_path)) {
    // 1. Clean the path and define the REAL location on your server
    $cleanPath    = trim($contract->file_path); 
    $absolutePath = "/home/portallogist/public_html/portallogistice/build/storage/" . $cleanPath;
    $fileName     = basename($cleanPath);

    Log::info('Phase 4: Starting Auto-Sign', [
        'contract_id' => $contract->id,
        'path'        => $absolutePath,
    ]);

    try {
        // We use the absolute path to READ the file for Sadq
        $signResult = $this->sadqService->signDocument($requestId, $absolutePath, $fileName);

        if ($signResult['success'] && ! empty($signResult['signed_base64'])) {
            $decodedPdf = base64_decode($signResult['signed_base64']);
            
            // We use the SAME absolute path to WRITE the signed PDF back
            if (file_put_contents($absolutePath, $decodedPdf) !== false) {
                Log::info('Phase 4: Signed PDF successfully overwritten', [
                    'contract_id' => $contract->id,
                    'path'        => $absolutePath
                ]);
            } else {
                Log::error('Phase 4: Save failed. Check folder permissions.', ['path' => $absolutePath]);
            }
        } else {
            Log::error('Phase 4: Sadq signing failed', [
                'message' => $signResult['message'] ?? 'Unknown error'
            ]);
        }
    } catch (\Throwable $e) {
        Log::error('Phase 4: Critical Exception', ['error' => $e->getMessage()]);
    }
}
            else {
                Log::info('Phase 4: No file to sign, skipping signing step', [
                    'contract_id' => $contract->id,
                ]);
            }

            // Always move to admin_pending after Nafath approval (with or without signing)
            $contract->update(['status' => 'admin_pending']);

        } elseif ($status === SadqNafathRequest::STATUS_REJECTED) {
            // Reset to sent so user can try Nafath again
            $contract->update(['status' => 'sent']);

        } else {
            $contract->update(['status' => 'nafath_pending']);
        }

        $toStatus = $contract->fresh()->status;
        Log::info('Sadq webhook contract status change', [
            'contract_id' => $contract->id,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
        ]);

        Log::info('Sadq webhook processed', ['request_id' => $requestId, 'status' => $status]);

        return response()->json(['success' => true]);
    }

    protected function isAuthorizedWebhook(Request $request): bool
    {
        $allowedRaw = (string) config('services.sadq.webhook_ip_whitelist', '');
        $allowedIps = array_values(array_filter(array_map('trim', explode(',', $allowedRaw))));
        if ($allowedIps !== [] && ! in_array((string) $request->ip(), $allowedIps, true)) {
            return false;
        }

        $secret = (string) config('services.sadq.webhook_secret', '');
        if ($secret === '') {
            return true;
        }

        $provided = (string) (
            $request->header('X-Sadq-Webhook-Secret')
            ?? $request->header('X-Webhook-Secret')
            ?? ''
        );

        return $provided !== '' && hash_equals($secret, $provided);
    }

    protected function extractRequestId(array $payload): ?string
    {
        foreach ([
            data_get($payload, 'requestId'),
            data_get($payload, 'request_id'),
            data_get($payload, 'RequestId'),
            data_get($payload, 'data.requestId'),
            data_get($payload, 'data.request_id'),
        ] as $v) {
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
        return null;
    }

    protected function extractNationalId(array $payload): ?string
    {
        $v = data_get($payload, 'nationalId')
            ?? data_get($payload, 'national_id')
            ?? data_get($payload, 'nationalIds.0');

        if (is_string($v) && $v !== '') {
            return preg_replace('/\D/', '', $v) ?: null;
        }
        return null;
    }

    protected function normalizeStatus(array $payload): string
    {
        $numericStatus = data_get($payload, 'Status', data_get($payload, 'status'));
        if ($numericStatus !== null && is_numeric($numericStatus)) {
            $n = (int) $numericStatus;
            if ($n === 0) return SadqNafathRequest::STATUS_APPROVED;
            if ($n === 1) return SadqNafathRequest::STATUS_REJECTED;
        }

        $raw = strtolower(trim((string) (
            data_get($payload, 'status')
            ?? data_get($payload, 'Status')
            ?? data_get($payload, 'state')
            ?? data_get($payload, 'nafathStatus')
            ?? data_get($payload, 'data.status')
            ?? ''
        )));

        if ($raw === '') return SadqNafathRequest::STATUS_PENDING;
        if (str_contains($raw, 'approv') || in_array($raw, ['success', 'completed', 'complete'], true)) return SadqNafathRequest::STATUS_APPROVED;
        if (str_contains($raw, 'reject') || str_contains($raw, 'denied') || str_contains($raw, 'fail')) return SadqNafathRequest::STATUS_REJECTED;

        return SadqNafathRequest::STATUS_PENDING;
    }
}
