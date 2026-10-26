<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Setono\ClientBundle\Entity\MetadataInterface;

/**
 * The bundle maps its Metadata class as a mapped superclass, so an application can extend it with its own entity.
 * This listener makes sure that Doctrine sees the configured metadata class as an entity, i.e. the bundle's
 * Metadata class when the application doesn't configure its own
 */
final class ConvertToEntityListener
{
    /**
     * @param class-string<MetadataInterface> $metadataClass
     */
    public function __construct(private readonly string $metadataClass)
    {
    }

    /**
     * @param LoadClassMetadataEventArgs<\Doctrine\Persistence\Mapping\ClassMetadata<object>, \Doctrine\Persistence\ObjectManager> $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if ($metadata->getName() !== $this->metadataClass || !$metadata instanceof ClassMetadata) {
            return;
        }

        $metadata->isMappedSuperclass = false;
    }
}
