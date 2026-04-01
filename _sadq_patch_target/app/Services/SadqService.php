<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\SadqNafathRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SadqService
{
    protected function baseUrl(): string
    {
        return rtrim((string) config('services.sadq.base_url', 'https://api.sadq.sa'), '/');
    }

    protected function apiKey(): string
    {
        return (string) config('services.sadq.api_key', '');
    }

    protected function webhookUrl(): string
    {
        return (string) config('services.sadq.webhook_url', '');
    }

    protected function initiatePath(): string
    {
        $path = (string) config('services.sadq.nafath_initiate_path', '/nafath/initiate');

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    protected function timeoutSeconds(): int
    {
        return max(5, (int) config('services.sadq.timeout', 30));
    }

    /**
     * Real Sadq Nafath initiation.
     * Supports contract-first workflow and legacy national-id caller.
     *
     * @return array{
     *   success:bool,
     *   http_status:int,
     *   request_id:?string,
     *   challenge_number:?string,
     *   response:array<string,mixed>,
     *   message:string
     * }
     */
    public function initiateNafath(Contract|string $contractOrNationalId, ?string $contractType = null): array
    {
        $isContractFlow = $contractOrNationalId instanceof Contract;
        $contract = $isContractFlow ? $contractOrNationalId : null;

        $nationalId = $isContractFlow
            ? (string) ($contract->user?->national_id ?? '')
            : (string) $contractOrNationalId;
        $nationalId = preg_replace('/\D/', '', $nationalId) ?? '';

        if ($nationalId === '' || ! ctype_digit($nationalId)) {
            return [
                'success' => false,
                'http_status' => 422,
                'request_id' => null,
                'challenge_number' => null,
                'response' => [],
                'message' => 'Invalid national_id.',
            ];
        }

        $apiKey = $this->apiKey();
        $accountId = (string) config('services.sadq.account_id', '');
        $callbackUrl = $this->webhookUrl();

        if ($apiKey === '' || $accountId === '' || $callbackUrl === '') {
            return [
                'success' => false,
                'http_status' => 503,
                'request_id' => null,
                'challenge_number' => null,
                'response' => [],
                'message' => 'Sadq config missing (SADQ_API_KEY / SADQ_ACCOUNT_ID / SADQ_WEBHOOK_URL).',
            ];
        }

        $url = $this->baseUrl().$this->initiatePath();
        $referenceId = $isContractFlow ? (string) $contract->id : (string) Str::uuid();
        $requestBody = [
            'national_id' => $nationalId,
            'reference_id' => $referenceId,
            'callback_url' => $callbackUrl,
            'account_id' => $accountId,
        ];

        Log::info('Sadq Nafath initiate request', [
            'url' => $url,
            'contract_id' => $contract?->id,
            'reference_id' => $referenceId,
            'request_payload' => $requestBody,
        ]);

        try {
            $httpResponse = Http::timeout($this->timeoutSeconds())
                ->connectTimeout(min(15, $this->timeoutSeconds()))
                // Temporary SSL workaround for cURL error 60 (certificate chain issues).
                ->withOptions(['verify' => false])
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post($url, $requestBody);
        } catch (ConnectionException $e) {
            Log::error('Sadq Nafath initiate connection error', [
                'contract_id' => $contract?->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'http_status' => 0,
                'request_id' => null,
                'challenge_number' => null,
                'response' => [],
                'message' => 'Sadq API unreachable.',
            ];
        } catch (Throwable $e) {
            Log::error('Sadq Nafath initiate exception', [
                'contract_id' => $contract?->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'http_status' => 0,
                'request_id' => null,
                'challenge_number' => null,
                'response' => [],
                'message' => $e->getMessage(),
            ];
        }

        $responseJson = $httpResponse->json();
        if (! is_array($responseJson)) {
            $responseJson = ['raw' => (string) $httpResponse->body()];
        }

        Log::info('Sadq Nafath initiate response', [
            'contract_id' => $contract?->id,
            'http_status' => $httpResponse->status(),
            'response_payload' => $responseJson,
        ]);

        $requestId = $this->extractRequestId($responseJson);
        $challengeNumber = $this->extractChallengeNumber($responseJson);

        if (! $httpResponse->successful() || $requestId === null) {
            return [
                'success' => false,
                'http_status' => $httpResponse->status(),
                'request_id' => $requestId,
                'challenge_number' => $challengeNumber,
                'response' => $responseJson,
                'message' => (string) (
                    data_get($responseJson, 'message')
                    ?? data_get($responseJson, 'error')
                    ?? 'Sadq Nafath initiate failed.'
                ),
            ];
        }

        if ($isContractFlow && $contract !== null) {
            $contract->update([
                'nafath_reference' => $requestId,
                'status' => Contract::STATUS_NAFATH_PENDING,
            ]);
        }

        SadqNafathRequest::updateOrCreate(
            ['request_id' => $requestId],
            [
                'national_id' => $nationalId,
                'contract_type' => $contractType ?? ($isContractFlow ? $contract->type : null),
                'status' => SadqNafathRequest::STATUS_PENDING,
                'last_payload' => [
                    'request' => $requestBody,
                    'response' => $responseJson,
                    'contract_id' => $contract?->id,
                    'created_at' => now()->toIso8601String(),
                ],
            ]
        );

        return [
            'success' => true,
            'http_status' => $httpResponse->status(),
            'request_id' => $requestId,
            'challenge_number' => $challengeNumber,
            'response' => $responseJson,
            'message' => 'Nafath request sent successfully.',
        ];
    }

    /**
     * Kept for existing dashboard/status consumers.
     *
     * @return array{success: bool, status: string, request_id: ?string, message: ?string}
     */
    public function getLocalStatus(string $nationalId, ?string $contractType = null): array
    {
        $nationalId = preg_replace('/\D/', '', $nationalId) ?? '';
        $q = SadqNafathRequest::query()->where('national_id', $nationalId)->orderByDesc('id');
        if ($contractType !== null && $contractType !== '') {
            $q->where('contract_type', $contractType);
        }
        $row = $q->first();
        if (! $row) {
            return [
                'success' => true,
                'status' => 'not_found',
                'request_id' => null,
                'message' => null,
            ];
        }

        return [
            'success' => true,
            'status' => $row->status,
            'request_id' => $row->request_id,
            'message' => null,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    protected function extractRequestId(array $payload): ?string
    {
        foreach ([
            data_get($payload, 'request_id'),
            data_get($payload, 'requestId'),
            data_get($payload, 'data.request_id'),
            data_get($payload, 'data.requestId'),
            data_get($payload, 'id'),
        ] as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    protected function extractChallengeNumber(array $payload): ?string
    {
        foreach ([
            data_get($payload, 'challenge_number'),
            data_get($payload, 'challengeNumber'),
            data_get($payload, 'data.challenge_number'),
            data_get($payload, 'data.challengeNumber'),
        ] as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
