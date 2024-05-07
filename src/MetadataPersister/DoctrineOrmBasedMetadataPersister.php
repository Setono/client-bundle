<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataPersister;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Client\LazyChangeAwareMetadata;
use Setono\ClientBundle\Entity\MetadataInterface as MetadataEntityInterface;
use Setono\Doctrine\ORMTrait;

final class DoctrineOrmBasedMetadataPersister implements MetadataPersisterInterface
{
    use ORMTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataEntityInterface> $metadataEntityClass
         */
        private readonly string $metadataEntityClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function persist(Client $client): void
    {
        $manager = $this->getManager($this->metadataEntityClass);

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
