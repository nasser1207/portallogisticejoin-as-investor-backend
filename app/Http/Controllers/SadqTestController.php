<?php

namespace App\Http\Controllers;

use App\Services\SadqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Postman / QA — restrict or remove in production (e.g. middleware, IP allowlist).
 */
class SadqTestController extends Controller
{
    public function __construct(
        protected SadqService $sadq
    ) {}

    public function nafath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'national_id' => 'required|string|max:32',
        ]);

        $result = $this->sadq->initiateNafath($validated['national_id']);

        $http = (int) ($result['http_status'] ?? 200);
        if ($http === 0) {
            $http = 502;
        }

        return response()->json($result, $http >= 400 && $http < 600 ? $http : 200);
    }
}
