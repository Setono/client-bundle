<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\PendingMetadataStorage\PendingMetadataStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * When a client comes back with the pending metadata it was given as a new client,
 * this context hands it to the client, so it's persisted at the end of the request
 */
final class PendingMetadataClientContext implements ClientContextInterface
{
    public function __construct(
        private readonly ClientContextInterface $decorated,
        private readonly PendingMetadataStorageInterface $pendingMetadataStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getClient(): Client
    {
        $client = $this->decorated->getClient();

        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return $client;
        }

        $pendingMetadata = $this->pendingMetadataStorage->get($request, $client->id);

        // If the client already has metadata (e.g. because a concurrent request persisted the pending metadata),
        // that metadata is at least as new as the pending metadata
        if (null === $pendingMetadata || count($client->metadata) > 0) {
            return $client;
        }

        $metadata = new ChangeAwareMetadata($pendingMetadata);
        $metadata->markDirty(); // the pending metadata isn't persisted yet

        return new Client($client->id, $metadata);
    }
}
