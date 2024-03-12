<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Metadata;
use Setono\ClientBundle\Client\LazyMetadata;
use Setono\ClientBundle\Entity\MetadataInterface;

final class DoctrineOrmBasedMetadataProvider implements MetadataProviderInterface
{
    public function __construct(
        private readonly MetadataProviderInterface $decorated,
        private readonly ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataInterface> $metadataClass
         */
        private readonly string $metadataClass,
    ) {
    }

    public function getMetadata(string $clientId): Metadata
    {
        $manager = $this->managerRegistry->getManagerForClass($this->metadataClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->metadataClass));
        }

        return LazyMetadata::createLazyGhost(function (Metadata $instance) use ($clientId, $manager): void {
            /** @var MetadataInterface|null $entity */
            $entity = $manager->find($this->metadataClass, $clientId);

            if (null === $entity) {
                $metadata = $this->decorated->getMetadata($clientId)->toArray();
            } else {
                $metadata = $entity->getMetadata();
            }

            /** @psalm-suppress DirectConstructorCall */
            $instance->__construct($metadata);
        });
    }
}
