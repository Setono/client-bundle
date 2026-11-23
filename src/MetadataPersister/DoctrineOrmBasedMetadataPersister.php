<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataPersister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Client;
use Setono\ClientBundle\Client\LazyMetadata;
use Setono\ClientBundle\Entity\MetadataInterface as MetadataEntityInterface;
use Setono\Doctrine\ORMTrait;

final class DoctrineOrmBasedMetadataPersister implements MetadataPersisterInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,

        /** @var class-string<MetadataEntityInterface> $metadataEntityClass */
        private readonly string $metadataEntityClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function persist(Client $client): void
    {
        $manager = $this->getManager($this->metadataEntityClass);

        $metadata = $client->metadata;

        // Checked first, because calling hasChanged() on a lazy ghost would load it from the database
        if ($metadata instanceof LazyMetadata && !$metadata->isLazyObjectInitialized()) {
            return;
        }

        if (!$metadata->hasChanged()) {
            return;
        }

        $entity = $manager->find($this->metadataEntityClass, $client->id);
        if (null === $entity) {
            /** @var MetadataEntityInterface $entity */
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
