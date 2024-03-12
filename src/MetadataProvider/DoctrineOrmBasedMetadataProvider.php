<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Metadata;
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

        /** @var MetadataInterface|null $metadata */
        $metadata = $manager->find($this->metadataClass, $clientId);

        if (null === $metadata) {
            return $this->decorated->getMetadata($clientId);
        }

        return new Metadata($metadata->getMetadata());
    }
}
