<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Setono\ClientBundle\Entity\Metadata;

/**
 * The bundle maps its Metadata class as a mapped superclass, so an application can extend it with its own entity.
 * This listener makes Doctrine see the bundle's Metadata class as an entity. SetonoClientExtension only registers it
 * when the configured metadata class doesn't extend the bundle's class
 */
final class ConvertToEntityListener
{
    /**
     * @param LoadClassMetadataEventArgs<\Doctrine\Persistence\Mapping\ClassMetadata<object>, \Doctrine\Persistence\ObjectManager> $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if (Metadata::class !== $metadata->getName() || !$metadata instanceof ClassMetadata) {
            return;
        }

        $metadata->isMappedSuperclass = false;
    }
}
