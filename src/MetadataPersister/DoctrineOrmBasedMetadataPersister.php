<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataPersister;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Client\LazyChangeAwareMetadata;
use Setono\ClientBundle\Entity\MetadataInterface as MetadataEntityInterface;

final class DoctrineOrmBasedMetadataPersister implements MetadataPersisterInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataEntityInterface> $metadataEntityClass
         */
        private readonly string $metadataEntityClass,
    ) {
    }

    public function persist(Client $client): void
    {
        $manager = $this->managerRegistry->getManagerForClass($this->metadataEntityClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->metadataEntityClass));
        }

        $metadata = $client->metadata;

        if ($metadata instanceof LazyChangeAwareMetadata && !$metadata->isLazyObjectInitialized()) {
            return;
        }

        if ($metadata instanceof ChangeAwareMetadata && !$metadata->isDirty()) {
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

        $manager->flush();
    }
}
