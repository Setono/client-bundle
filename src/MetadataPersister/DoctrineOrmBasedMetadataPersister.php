<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataPersister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Client\LazyChangeAwareMetadata;
use Setono\ClientBundle\Entity\MetadataInterface as MetadataEntityInterface;
use Setono\Doctrine\ORMTrait;

final class DoctrineOrmBasedMetadataPersister implements MetadataPersisterInterface, LoggerAwareInterface
{
    use ORMTrait;

    private LoggerInterface $logger;

    public function __construct(
        ManagerRegistry $managerRegistry,

        /** @var class-string<MetadataEntityInterface> $metadataEntityClass */
        private readonly string $metadataEntityClass,
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->logger = new NullLogger();
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

        try {
            $manager->flush();
        } catch (UniqueConstraintViolationException) {
            // todo how to handle this?

            $this->logger->error(sprintf('Unique constraint violation occurred while persisting metadata for client id %s', $client->id));
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
