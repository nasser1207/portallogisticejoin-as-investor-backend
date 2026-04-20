<?php

namespace App\Http\Controllers;

use App\Models\SadqNafathRequest;
use App\Services\NafathSigningPipeline;
use App\Services\SignableRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Sadq/Nafath webhook callbacks.
 *
 * This controller is now entity-agnostic: it does not import Contract or
 * InvestorRequest. It delegates all model-level work to the pipeline + registry.
 *
 * Adding a new signable type in the future = zero changes here.
 */
class SadqWebhookController extends Controller
{
    public function __construct(
        private readonly SignableRegistry     $registry,
        private readonly NafathSigningPipeline $pipeline
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload  = $request->all();
        $sourceIp = (string) $request->ip();

        Log::info('Sadq webhook received', ['ip' => $sourceIp, 'payload' => $payload]);

        if (! $this->isAuthorizedWebhook($request)) {
            Log::warning('Sadq webhook unauthorized', ['ip' => $sourceIp]);
            return response()->json(['success' => false, 'message' => 'Unauthorized webhook source.'], 401);
        }

        $requestId = $this->extractRequestId($payload);
        $status    = $this->normalizeStatus($payload);

        Log::info('Sadq webhook parsed', [
            'request_id' => $requestId,
            'status'     => $status,
        ]);

        if ($requestId === null || $requestId === '') {
            Log::warning('Sadq webhook: missing requestId', ['keys' => array_keys($payload)]);
            return response()->json(['success' => true]);
        }

        // ── Persist the raw webhook event ─────────────────────────────────────
        $this->upsertNafathRecord($requestId, $status, $payload);

        // ── Resolve the Signable entity (contract, investor_request, etc.) ────
        $signable = $this->registry->resolve($requestId);

        if ($signable === null) {
            Log::warning('Sadq webhook: no entity found for reference', ['request_id' => $requestId]);
            return response()->json(['success' => true]);
        }

        Log::info('Sadq webhook: entity resolved', [
            'type'   => $signable->getSignableType(),
            'id'     => $signable->getSignableId(),
            'status' => $status,
        ]);

        // ── Dispatch to the pipeline ──────────────────────────────────────────
        match ($status) {
            SadqNafathRequest::STATUS_APPROVED => $this->pipeline->handleApproved($signable, $requestId),
            SadqNafathRequest::STATUS_REJECTED => $this->pipeline->handleRejected($signable),
            default                            => null, // pending — no action needed
        };

        Log::info('Sadq webhook processed', ['request_id' => $requestId, 'status' => $status]);

        return response()->json(['success' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function upsertNafathRecord(string $requestId, string $status, array $payload): void
    {
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
