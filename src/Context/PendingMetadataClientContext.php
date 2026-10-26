<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Context;

use Setono\Client\Client;
use Setono\Client\Metadata;
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
        if (null === $pendingMetadata) {
            return $client;
        }

        [$data, $expires] = self::split($client->metadata);
        [$pendingData, $pendingExpires] = self::split(new Metadata($pendingMetadata));

        // A key the client already has wins, because it was persisted from metadata that held the pending metadata
        // (e.g. by a concurrent request), so it's at least as new. Other keys, e.g. persisted by the application
        // outside the client's requests, are kept
        $missingData = array_diff_key($pendingData, $data);
        if ([] === $missingData) {
            return $client;
        }

        $metadata = new ChangeAwareMetadata($data + $missingData + [
            Metadata::EXPIRES_KEY => $expires + array_intersect_key($pendingExpires, $missingData),
        ]);
        $metadata->markDirty(); // the pending metadata isn't persisted yet

        return new Client($client->id, $metadata);
    }

    /**
     * @return array{array<string, mixed>, array<string, int>} the data and the expiry timestamps of the metadata
     */
    private static function split(Metadata $metadata): array
    {
        $data = $metadata->toArray();

        /** @var array<string, int> $expires */
        $expires = $data[Metadata::EXPIRES_KEY] ?? [];
        unset($data[Metadata::EXPIRES_KEY]);

        return [$data, $expires];
    }
}
