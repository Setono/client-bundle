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
         * @var class-string<MetadataInterface> $metadataClass
         */
        private readonly string $metadataClass,
    ) {
    }

    public function persist(Client $client): void
    {
        $manager = $this->managerRegistry->getManagerForClass($this->metadataClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->metadataClass));
        }

        $entity = $manager->find($this->metadataClass, $client->id);
        if (null === $entity) {
            /** @var MetadataInterface $entity */
            $entity = new $this->metadataClass();
            $entity->setClientId($client->id);

            $manager->persist($entity);
        }

        $entity->setMetadata($client->metadata->toArray());

        $manager->flush();
    }
}
