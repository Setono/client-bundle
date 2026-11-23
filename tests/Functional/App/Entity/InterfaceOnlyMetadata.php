<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\Entity;

use Setono\ClientBundle\Entity\MetadataInterface;

/**
 * An application's own metadata entity that implements the interface without extending the bundle's Metadata class
 */
class InterfaceOnlyMetadata implements MetadataInterface
{
    private ?string $clientId = null;

    /** @var array<string, mixed>|null */
    private ?array $metadata = null;

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = [] === $metadata ? null : $metadata;
    }
}
