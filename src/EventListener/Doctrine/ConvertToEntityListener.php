<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Setono\ClientBundle\Entity\MetadataInterface;

final class ConvertToEntityListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        if (!is_a($eventArgs->getClassMetadata()->getName(), MetadataInterface::class, true)) {
            return;
        }

        $metadata = $eventArgs->getClassMetadata();
        if (!$metadata instanceof ClassMetadata) {
            return;
        }

        $metadata->isMappedSuperclass = false;
    }
}
