<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataStore;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Client;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\LazyMetadata;
use Setono\ClientBundle\Entity\MetadataInterface as MetadataEntityInterface;
use Setono\Doctrine\ORMTrait;

/**
 * Stores the metadata of each client in a row of the configured metadata entity. A client without a row has empty metadata
 */
final class DoctrineOrmBasedMetadataStore implements MetadataStoreInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,

        /** @var class-string<MetadataEntityInterface> $metadataEntityClass */
        private readonly string $metadataEntityClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function load(string $clientId): Metadata
    {
        // The manager is looked up when the metadata is used, so a request that doesn't use it doesn't start the entity manager
        return LazyMetadata::createLazyGhost(function (Metadata $instance) use ($clientId): void {
            $entity = $this->getManager($this->metadataEntityClass)->find($this->metadataEntityClass, $clientId);

            $instance->__construct($entity?->getMetadata() ?? []);
        });
    }

    public function store(Client $client): void
    {
        $metadata = $client->metadata;

        // Checked first, because calling hasChanged() on a lazy ghost would load it from the database
        if ($metadata instanceof LazyMetadata && !$metadata->isLazyObjectInitialized()) {
            return;
        }

        if (!$metadata->hasChanged()) {
            return;
        }

        $manager = $this->getManager($this->metadataEntityClass);

        $entity = $manager->find($this->metadataEntityClass, $client->id);
        if (null === $entity) {
            $entity = new $this->metadataEntityClass();
            $entity->setClientId($client->id);

            $manager->persist($entity);
        }

        $entity->setMetadata($metadata->toArray());

        try {
            $manager->flush();
        } catch (UniqueConstraintViolationException) {
        }
    }
}
