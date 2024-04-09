<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Setono\ClientBundle\Client\ChangeAwareMetadata;

final class EmptyMetadataProvider implements MetadataProviderInterface
{
    public function getMetadata(string $clientId): ChangeAwareMetadata
    {
        return new ChangeAwareMetadata();
    }
}
