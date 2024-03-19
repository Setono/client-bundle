<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataPersister;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Client;
use Setono\ClientBundle\Entity\MetadataInterface;

final class DoctrineOrmBasedMetadataPersister implements MetadataPersisterInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataInterface> $metadataEntityClass
         */
        private readonly string $metadataEntityClass,
    ) {
    }

    // todo we need a way to NOT persist if the metadata wasn't fetched from the db in the first place and is empty
    public function persist(Client $client): void
    {
        $manager = $this->managerRegistry->getManagerForClass($this->metadataEntityClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->metadataEntityClass));
        }

        $entity = $manager->find($this->metadataEntityClass, $client->id);
        if (null === $entity) {
            /** @var MetadataInterface $entity */
            $entity = new $this->metadataEntityClass();
            $entity->setClientId($client->id);

            $manager->persist($entity);
        }

        $entity->setMetadata($client->metadata->toArray());

        $manager->flush();
    }
}
