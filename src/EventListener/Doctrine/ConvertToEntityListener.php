<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Setono\ClientBundle\Entity\Client;

final class ConvertToEntityListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        if ($eventArgs->getClassMetadata()->getName() !== Client::class) {
            return;
        }

        $metadata = $eventArgs->getClassMetadata();
        if (!$metadata instanceof ClassMetadata) {
            return;
        }

        $metadata->isMappedSuperclass = false;
    }
}
