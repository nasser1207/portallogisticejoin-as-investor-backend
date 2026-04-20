<?php

namespace App\Services;

use App\Contracts\Signable;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full Nafath → sign → complete pipeline for ANY Signable.
 *
 * Single Responsibility: this class owns ONLY the orchestration logic.
 * It delegates Nafath API calls to SadqService and model transitions to
 * the Signable itself — neither knows about the other's internals.
 */
final class NafathSigningPipeline
{
    public function __construct(
        private readonly SadqService $sadqService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 1: Initiate (called from any controller's "nafath" button action)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initiates Nafath authentication for any Signable entity.
     * Returns the SadqService result array directly.
     */
    public function initiate(Signable $signable): array
    {
        $nationalId = preg_replace('/\D/', '', $signable->getSignableNationalId()) ?? '';

        if ($nationalId === '' || ! ctype_digit($nationalId)) {
            return [
                'success' => false,
                'message' => 'رقم الهوية الوطنية مطلوب للتوثيق عبر نفاذ.',
            ];
        }

        Log::info('NafathSigningPipeline: initiating', [
            'type' => $signable->getSignableType(),
            'id'   => $signable->getSignableId(),
        ]);

        // SadqService accepts a national ID string directly (non-contract flow)
        $result = $this->sadqService->initiateNafath($nationalId, $signable->getSignableType());

        if ($result['success'] ?? false) {
            // Persist the reference and update status on the model
            $signable->storeNafathReference($result['request_id']);

            Log::info('NafathSigningPipeline: initiated successfully', [
                'type'       => $signable->getSignableType(),
                'id'         => $signable->getSignableId(),
                'request_id' => $result['request_id'],
            ]);
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 2: Webhook received — approved
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called by the webhook when Nafath status = approved.
     * Transitions the model, signs the file if one exists, then finalises.
     */
    public function handleApproved(Signable $signable, string $requestId): void
    {
        Log::info('NafathSigningPipeline: handling approval', [
            'type' => $signable->getSignableType(),
            'id'   => $signable->getSignableId(),
        ]);

        $signable->onNafathApproved();

        $filePath = $signable->getSignableFilePath();

        if (! empty($filePath) && file_exists($filePath)) {
            $this->attemptSigning($signable, $requestId, $filePath);
        } else {
            Log::info('NafathSigningPipeline: no file to sign, skipping', [
                'type' => $signable->getSignableType(),
                'id'   => $signable->getSignableId(),
                'path' => $filePath,
            ]);
        }

        // Always advance to the final status regardless of signing outcome
        $signable->onSigningComplete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 3: Webhook received — rejected
    // ─────────────────────────────────────────────────────────────────────────

    public function handleRejected(Signable $signable): void
    {
        Log::info('NafathSigningPipeline: handling rejection', [
            'type' => $signable->getSignableType(),
            'id'   => $signable->getSignableId(),
        ]);

        $signable->onNafathRejected();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function attemptSigning(Signable $signable, string $requestId, string $filePath): void
    {
        $fileName = basename($filePath);

        Log::info('NafathSigningPipeline: signing document', [
            'type'      => $signable->getSignableType(),
            'id'        => $signable->getSignableId(),
            'file_path' => $filePath,
        ]);

        try {
            $signResult = $this->sadqService->signDocument($requestId, $filePath, $fileName);

            if ($signResult['success'] && ! empty($signResult['signed_base64'])) {
                $decoded = base64_decode($signResult['signed_base64']);

                if (file_put_contents($filePath, $decoded) !== false) {
                    Log::info('NafathSigningPipeline: signed PDF saved', [
                        'type' => $signable->getSignableType(),
                        'id'   => $signable->getSignableId(),
                        'path' => $filePath,
                    ]);
                } else {
                    Log::error('NafathSigningPipeline: failed to write signed PDF', [
                        'path' => $filePath,
                    ]);
                }
            } else {
                Log::error('NafathSigningPipeline: Sadq signing returned failure', [
                    'type'    => $signable->getSignableType(),
                    'id'      => $signable->getSignableId(),
                    'message' => $signResult['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('NafathSigningPipeline: signing exception', [
                'type'  => $signable->getSignableType(),
                'id'    => $signable->getSignableId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
