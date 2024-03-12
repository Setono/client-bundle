<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Setono\Client\Metadata;

interface MetadataProviderInterface
{
    /**
     * Returns metadata for the given client
     */
    public function getMetadata(string $clientId): Metadata;
}
