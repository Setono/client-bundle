<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\MetadataStore;

use Setono\Client\Client;
use Setono\Client\Metadata;
use Setono\ClientBundle\MetadataStore\MetadataStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Decorates the interface, like an application that adds behavior to the metadata store. It passes every call on and
 * records the client ids it was called with, so it doesn't change what the other tests see
 */
#[AsDecorator(MetadataStoreInterface::class)]
final class TraceableMetadataStore implements MetadataStoreInterface
{
    /** @var list<string> */
    public array $loadedClientIds = [];

    /** @var list<string> */
    public array $storedClientIds = [];

    public function __construct(
        #[AutowireDecorated]
        private readonly MetadataStoreInterface $decorated,
    ) {
    }

    public function load(string $clientId): Metadata
    {
        $this->loadedClientIds[] = $clientId;

        return $this->decorated->load($clientId);
    }

    public function store(Client $client): void
    {
        $this->storedClientIds[] = $client->id;

        $this->decorated->store($client);
    }
}
