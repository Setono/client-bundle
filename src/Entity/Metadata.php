<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Entity;

class Metadata implements MetadataInterface
{
    protected ?string $clientId = null;

    /** @var null|array{__expires?: array<string, int>, ...<string, mixed>} */
    protected ?array $metadata = [];

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
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
