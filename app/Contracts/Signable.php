<?php

namespace App\Contracts;

/**
 * Any model that can be signed via Sadq/Nafath must implement this.
 * Follows the Interface Segregation Principle — only signing concerns live here.
 */
interface Signable
{
    /**
     * The database ID of this signable entity.
     */
    public function getSignableId(): int;

    /**
     * A short discriminator stored alongside the nafath_reference
     * so the webhook knows which resolver to use.
     *
     * Examples: 'contract', 'investor_request'
     */
    public function getSignableType(): string;

    /**
     * Absolute server path to the PDF that should be signed.
     */
    public function getSignableFilePath(): string;

    /**
     * The national ID of the person who must approve in Nafath.
     */
    public function getSignableNationalId(): string;

    /**
     * Called by the webhook when Nafath approves — before signing.
     * Typically updates status to something like 'nafath_approved'.
     */
    public function onNafathApproved(): void;

    /**
     * Called by the webhook after the PDF has been signed successfully.
     * Typically moves to the next workflow status.
     */
    public function onSigningComplete(): void;

    /**
     * Called by the webhook when Nafath rejects.
     * Should reset the entity so the user can retry.
     */
    public function onNafathRejected(): void;

    /**
     * Where to persist the nafath_reference UUID for later webhook lookup.
     */
    public function storeNafathReference(string $requestId): void;

    /**
     * Retrieve the currently stored nafath_reference, if any.
     */
    public function getNafathReference(): ?string;
}
