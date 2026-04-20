<?php

namespace App\Contracts;

/**
 * A resolver knows how to load a Signable model from a nafath_reference UUID.
 * One resolver per signable type — Open/Closed Principle at work.
 */
interface SignableResolver
{
    /**
     * The discriminator string this resolver handles.
     * Must match Signable::getSignableType() on its model.
     */
    public function supports(): string;

    /**
     * Load the Signable entity identified by the given nafath_reference UUID.
     * Returns null if not found.
     */
    public function resolve(string $nafathReference): ?Signable;
}
