<?php

namespace App\Resolvers;

use App\Contracts\Signable;
use App\Contracts\SignableResolver;
use App\Models\Contract;

/**
 * Resolves a nafath_reference to a Contract model.
 * This is the existing flow — zero behaviour change.
 */
final class ContractSignableResolver implements SignableResolver
{
    public function supports(): string
    {
        return 'contract';
    }

    public function resolve(string $nafathReference): ?Signable
    {
        return Contract::where('nafath_reference', $nafathReference)->first();
    }
}
