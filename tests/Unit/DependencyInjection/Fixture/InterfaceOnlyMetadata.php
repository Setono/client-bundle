<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Unit\DependencyInjection\Fixture;

use Setono\ClientBundle\Entity\MetadataInterface;

/**
 * A metadata class that implements the interface without extending the bundle's Metadata class
 */
final class InterfaceOnlyMetadata implements MetadataInterface
{
    public function getClientId(): ?string
    {
        return null;
    }

    public function setClientId(string $clientId): void
    {
    }

    public function getMetadata(): array
    {
        return [];
    }

    public function setMetadata(array $metadata): void
    {
    }
}
