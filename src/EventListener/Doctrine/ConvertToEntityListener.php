<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;

final class ConvertToEntityListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if (!$metadata instanceof ClassMetadata) {
            return;
        }

        $metadata->isMappedSuperclass = false;
    }
}
