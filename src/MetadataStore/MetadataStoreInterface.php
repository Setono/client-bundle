<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataStore;

use Setono\Client\Client;
use Setono\Client\Metadata;

/**
 * Loads and stores the metadata of clients. The bundle stores the metadata of the current client at the end of every
 * main request, so every implementation must keep a request that doesn't change the metadata cheap
 */
interface MetadataStoreInterface
{
    /**
     * Returns the metadata of the given client. The returned metadata must not touch the storage until it's used
     */
    public function load(string $clientId): Metadata;

    /**
     * Stores the metadata of the given client, but only when it changed (see Metadata::hasChanged()). Metadata that
     * load() returned and that was never used must not be loaded to find out
     */
    public function store(Client $client): void;
}
