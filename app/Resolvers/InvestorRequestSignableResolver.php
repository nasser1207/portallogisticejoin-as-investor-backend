<?php

namespace App\Resolvers;

use App\Contracts\Signable;
use App\Contracts\SignableResolver;
use App\Models\InvestorRequest;

/**
 * Resolves a nafath_reference to an InvestorRequest model.
 * New flow — no existing code touched.
 */
final class InvestorRequestSignableResolver implements SignableResolver
{
    public function supports(): string
    {
        return 'investor_request';
    }

    public function resolve(string $nafathReference): ?Signable
    {
        return InvestorRequest::where('nafath_reference', $nafathReference)->first();
    }
}
