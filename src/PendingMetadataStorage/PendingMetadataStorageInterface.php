<?php

declare(strict_types=1);

namespace Setono\ClientBundle\PendingMetadataStorage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds the metadata of a new client until the client proves that it keeps its cookie, i.e. until it comes back.
 * That way clients that never come back (crawlers, link checkers, cookie blocking browsers) never produce a database row
 */
interface PendingMetadataStorageInterface
{
    /**
     * Returns the pending metadata the request carries for the given client, or null if it carries none
     *
     * @return array<string, mixed>|null
     */
    public function get(Request $request, string $clientId): ?array;

    /**
     * Returns false if the metadata cannot be stored (e.g. because it is too big), in which case it must be persisted right away
     *
     * @param array<string, mixed> $metadata the metadata as returned by Metadata::toArray()
     */
    public function store(Request $request, Response $response, string $clientId, array $metadata): bool;

    /**
     * Removes the pending metadata the request carries, if any
     */
    public function remove(Request $request, Response $response): void;
}
