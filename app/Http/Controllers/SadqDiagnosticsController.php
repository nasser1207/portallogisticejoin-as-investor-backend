<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\SadqNafathRequest;
use Illuminate\Http\JsonResponse;

class SadqDiagnosticsController extends Controller
{
    public function healthCheck(): JsonResponse
    {
        $latest = SadqNafathRequest::query()->latest('id')->first();
        $contract = null;

        if ($latest?->request_id) {
            $contract = Contract::query()
                ->where('nafath_reference', $latest->request_id)
                ->first();
        }

        $webhookUrl = (string) config('services.sadq.webhook_url', '');
        $baseUrl = (string) config('services.sadq.base_url', '');
        $path = (string) config('services.sadq.nafath_initiate_path', '/nafath/initiate');
        $apiKey = (string) config('services.sadq.api_key', '');
        $fullEndpoint = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        return response()->json([
            'success' => true,
            'data' => [
                'sadq_config' => [
                    'base_url' => $baseUrl,
                    'nafath_initiate_path' => $path,
                    'nafath_endpoint' => $fullEndpoint,
                    'webhook_url' => $webhookUrl,
                    'api_key_present' => $apiKey !== '',
                    'api_key_length' => strlen($apiKey),
                    'ssl_verify_enabled' => (bool) config('services.sadq.verify_ssl', true),
                ],
                'latest_attempt' => $latest ? [
                    'request_id' => $latest->request_id,
                    'status' => $latest->status,
                    'national_id' => $latest->national_id,
                    'contract_type' => $latest->contract_type,
                    'created_at' => optional($latest->created_at)->toIso8601String(),
                    'updated_at' => optional($latest->updated_at)->toIso8601String(),
                    'response_excerpt' => data_get($latest->last_payload, 'response'),
                ] : null,
                'matched_contract' => $contract ? [
                    'id' => $contract->id,
                    'user_id' => $contract->user_id,
                    'type' => $contract->type,
                    'status' => $contract->status,
                    'nafath_reference' => $contract->nafath_reference,
                    'updated_at' => optional($contract->updated_at)->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
