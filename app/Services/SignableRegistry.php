<?php

namespace App\Services;

use App\Contracts\Signable;
use App\Contracts\SignableResolver;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Registry of all SignableResolvers.
 *
 * Resolves a nafath_reference to its Signable model by asking each
 * registered resolver whether it owns that reference.
 *
 * Design patterns used:
 *   - Registry  : stores all resolvers in one place
 *   - Chain of Responsibility : tries each resolver in turn
 *   - Open/Closed : adding a new signable type = register a new resolver, zero changes here
 */
final class SignableRegistry
{
    /** @var SignableResolver[] */
    private array $resolvers = [];

    public function register(SignableResolver $resolver): void
    {
        $this->resolvers[$resolver->supports()] = $resolver;
    }

    /**
     * Find which Signable owns this nafath_reference.
     * Returns null when no resolver claims it (orphan webhook).
     */
    public function resolve(string $nafathReference): ?Signable
    {
        foreach ($this->resolvers as $type => $resolver) {
            $signable = $resolver->resolve($nafathReference);
            if ($signable !== null) {
                Log::debug('SignableRegistry: resolved', [
                    'type'      => $type,
                    'id'        => $signable->getSignableId(),
                    'reference' => $nafathReference,
                ]);
                return $signable;
            }
        }

        Log::warning('SignableRegistry: no resolver claimed reference', [
            'reference' => $nafathReference,
            'resolvers' => array_keys($this->resolvers),
        ]);

        return null;
    }

    /**
     * Retrieve a specific resolver by type discriminator.
     * Useful if you need the resolver directly (e.g. in tests).
     */
    public function getResolver(string $type): SignableResolver
    {
        return $this->resolvers[$type]
            ?? throw new RuntimeException("No resolver registered for type [{$type}].");
    }
}
