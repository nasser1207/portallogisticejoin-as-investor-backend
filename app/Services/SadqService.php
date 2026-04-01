<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\SadqNafathRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SadqService
{
    protected function baseUrl(): string
    {
        return 'https://apigw.sadq.sa';
    }

    protected function accountId(): string
    {
        return (string) config('services.sadq.account_id', '');
    }

    protected function thumbPrint(): string
    {
        return (string) config('services.sadq.thumbprint', '');
    }

    protected function webhookUrl(): string
    {
        return (string) config('services.sadq.webhook_url', '');
    }

    protected function timeoutSeconds(): int
    {
        return max(5, (int) config('services.sadq.timeout', 30));
    }

    /**
     * Step 1: Initiate Nafath authentication
     * POST /Authentication/Authority/IntegrationNafathAuth
     */
    public function initiateNafath(Contract|string $contractOrNationalId, ?string $contractType = null): array
    {
        $isContractFlow = $contractOrNationalId instanceof Contract;
        $contract = $isContractFlow ? $contractOrNationalId : null;

        $nationalId = $isContractFlow
            ? (string) ($contract->user?->national_id ?? '')
            : (string) $contractOrNationalId;
        $nationalId = preg_replace('/\D/', '', $nationalId) ?? '';

        if ($nationalId === '' || !ctype_digit($nationalId)) {
            return $this->errorResult(422, 'Invalid national_id.');
        }

        $accountId  = $this->accountId();
        $thumbPrint = $this->thumbPrint();
        $webhookUrl = $this->webhookUrl();

        if ($accountId === '' || $thumbPrint === '' || $webhookUrl === '') {
            return $this->errorResult(503, 'Sadq config missing (SADQ_ACCOUNT_ID / SADQ_THUMBPRINT / SADQ_WEBHOOK_URL).');
        }

        $requestId = (string) Str::uuid();
        $url = $this->baseUrl() . '/Authentication/Authority/IntegrationNafathAuth';

        $body = [
            'nationalIds' => [$nationalId],
            'requestId'   => $requestId,
            'accountId'   => $accountId,
            'webHookUrl'  => $webhookUrl,
        ];

        Log::info('Sadq Nafath initiate request', [
            'url'         => $url,
            'contract_id' => $contract?->id,
            'request_id'  => $requestId,
            'body'        => $body,
        ]);

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'thumbPrint'   => $thumbPrint,
                    'accountId'    => $accountId,
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept'       => 'application/json',
                ])
                ->post($url, $body);
        } catch (Throwable $e) {
            Log::error('Sadq Nafath initiate exception', ['error' => $e->getMessage()]);
            return $this->errorResult(0, 'Sadq API unreachable: ' . $e->getMessage());
        }

        $json = $response->json();
        if (!is_array($json)) {
            $json = ['raw' => (string) $response->body()];
        }

        Log::info('Sadq Nafath initiate response', [
            'contract_id' => $contract?->id,
            'http_status' => $response->status(),
            'response'    => $json,
        ]);

        // Response is array of results per nationalId
        $result = is_array($json) && isset($json[0]) ? $json[0] : $json;
        $transId  = (string) ($result['transId'] ?? '');
        $random   = (string) ($result['random'] ?? '');
        $error    = strtolower((string) ($result['error'] ?? ''));

        if (!$response->successful() || $transId === '' || $error !== 'success') {
            return [
                'success'          => false,
                'http_status'      => $response->status(),
                'request_id'       => null,
                'trans_id'         => null,
                'challenge_number' => null,
                'response'         => $json,
                'message'          => $result['message'] ?? $result['error'] ?? 'Nafath initiation failed.',
            ];
        }

        // Update contract status
        if ($isContractFlow && $contract !== null) {
            $contract->update([
                'nafath_reference' => $requestId,
                'status'           => Contract::STATUS_NAFATH_PENDING,
            ]);
        }

        // Save request record
        SadqNafathRequest::updateOrCreate(
            ['request_id' => $requestId],
            [
                'national_id'   => $nationalId,
                'contract_type' => $contractType ?? ($isContractFlow ? $contract->type : null),
                'status'        => SadqNafathRequest::STATUS_PENDING,
                'last_payload'  => [
                    'request'     => $body,
                    'response'    => $json,
                    'trans_id'    => $transId,
                    'contract_id' => $contract?->id,
                    'created_at'  => now()->toIso8601String(),
                ],
            ]
        );

        return [
            'success'          => true,
            'http_status'      => $response->status(),
            'request_id'       => $requestId,
            'trans_id'         => $transId,
            'challenge_number' => $random,
            'response'         => $json,
            'message'          => 'Nafath request sent successfully.',
        ];
    }

    /**
     * Step 2: Sign document after Nafath approval
     * POST /IntegrationService/SadqESign/Nafath/Sign
     */
    public function signDocument(string $requestId, string $filePath, string $fileName): array
    {
        $accountId  = $this->accountId();
        $thumbPrint = $this->thumbPrint();

        if (!file_exists($filePath)) {
            return $this->errorResult(422, 'File not found: ' . $filePath);
        }

        $fileBase64 = base64_encode(file_get_contents($filePath));
        $url = $this->baseUrl() . '/IntegrationService/SadqESign/Nafath/Sign';

        $body = [
            'RequestId' => $requestId,
            'File' => [
                'fileName' => $fileName,
                'File'     => $fileBase64,
            ],
        ];

        Log::info('Sadq sign document request', [
            'request_id' => $requestId,
            'file_name'  => $fileName,
            
        ]);

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->withOptions(['verify' => false])
               ->withHeaders([
        'thumbPrint'   => $thumbPrint,
        'accountId'    => $accountId,    // Exact casing from Postman
        'Content-Type' => 'application/json',
        'accept'       => 'text/plain',  // Postman uses text/plain here
    ])
                ->post($url, $body);
        } catch (Throwable $e) {
            Log::error('Sadq sign document exception', ['error' => $e->getMessage()]);
            return $this->errorResult(0, 'Sadq sign API unreachable: ' . $e->getMessage());
        }

        $json = $response->json();

        Log::info('Sadq sign document response', [
            'request_id'  => $requestId,
            'http_status' => $response->status(),
            'error_code'  => $json['errorCode'] ?? null,
            'message'     => $json['Message'] ?? null,
        ]);

        if (!$response->successful() || ($json['errorCode'] ?? 100) !== 0) {
            return [
                'success'     => false,
                'http_status' => $response->status(),
                'message'     => $json['Message'] ?? 'Sign failed.',
                'response'    => $json,
            ];
        }

        return [
            'success'      => true,
            'http_status'  => $response->status(),
            'signed_base64'=> $json['data'] ?? null,
            'message'      => $json['Message'] ?? 'Signed successfully.',
            'response'     => $json,
        ];
    }

    public function getLocalStatus(string $nationalId, ?string $contractType = null): array
    {
        $nationalId = preg_replace('/\D/', '', $nationalId) ?? '';
        $q = SadqNafathRequest::query()->where('national_id', $nationalId)->orderByDesc('id');
        if ($contractType !== null && $contractType !== '') {
            $q->where('contract_type', $contractType);
        }
        $row = $q->first();
        if (!$row) {
            return ['success' => true, 'status' => 'not_found', 'request_id' => null, 'message' => null];
        }
        return ['success' => true, 'status' => $row->status, 'request_id' => $row->request_id, 'message' => null];
    }

    protected function errorResult(int $httpStatus, string $message): array
    {
        return [
            'success'          => false,
            'http_status'      => $httpStatus,
            'request_id'       => null,
            'trans_id'         => null,
            'challenge_number' => null,
            'response'         => [],
            'message'          => $message,
        ];
    }
}