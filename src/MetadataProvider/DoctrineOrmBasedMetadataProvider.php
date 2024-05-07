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
        $manager = $this->getManager($this->metadataEntityClass);

        return LazyChangeAwareMetadata::createLazyGhost(function (Metadata $instance) use ($clientId, $manager): void {
            /** @var MetadataInterface|null $entity */
            $entity = $manager->find($this->metadataEntityClass, $clientId);

            if (null === $entity) {
                $metadata = $this->decorated->getMetadata($clientId)->toArray();
            } else {
                $metadata = $entity->getMetadata();
            }

            /**
             * todo remove the MixedArgumentTypeCoercion suppression when this issue is fixed: https://github.com/vimeo/psalm/issues/10964
             *
             * @psalm-suppress DirectConstructorCall,MixedArgumentTypeCoercion
             */
            $instance->__construct($metadata);
        });
    }
}
