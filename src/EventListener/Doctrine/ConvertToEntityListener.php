<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Setono\ClientBundle\Entity\Metadata;

/**
 * If the user of the plugin doesn't add their own Metadata,
 * this listener will make sure that Doctrine sees our Metadata as an entity
 */
final class ConvertToEntityListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        if (!is_a($eventArgs->getClassMetadata()->getName(), Metadata::class, true)) {
            return;
        }

        $metadata = $eventArgs->getClassMetadata();
        if (!$metadata instanceof ClassMetadata) {
            return;
        }

        $metadata->isMappedSuperclass = false;
    }
}
