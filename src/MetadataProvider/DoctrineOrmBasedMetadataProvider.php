<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\LazyChangeAwareMetadata;
use Setono\ClientBundle\Entity\MetadataInterface;
use Setono\Doctrine\ORMTrait;

final class DoctrineOrmBasedMetadataProvider implements MetadataProviderInterface
{
    use ORMTrait;

    public function __construct(
        private readonly MetadataProviderInterface $decorated,
        ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataInterface> $metadataEntityClass
         */
        private readonly string $metadataEntityClass,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getMetadata(string $clientId): Metadata
    {
        // The manager is looked up when the metadata is used, so a request that doesn't use it doesn't start the entity manager
        return LazyChangeAwareMetadata::createLazyGhost(function (Metadata $instance) use ($clientId): void {
            /** @var MetadataInterface|null $entity */
            $entity = $this->getManager($this->metadataEntityClass)->find($this->metadataEntityClass, $clientId);

            if (null === $entity) {
                $metadata = $this->decorated->getMetadata($clientId)->toArray();
            } else {
                $metadata = $entity->getMetadata();
            }

            $instance->__construct($metadata);
        });
    }
}
