<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Setono\Client\Metadata;
use Setono\ClientBundle\Client\ChangeAwareMetadata;

final class EmptyMetadataProvider implements MetadataProviderInterface
{
    public function getMetadata(string $clientId): Metadata
    {
        return new ChangeAwareMetadata();
    }
}
