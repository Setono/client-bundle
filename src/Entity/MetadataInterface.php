<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Entity;

interface MetadataInterface
{
    public function getClientId(): ?string;

    public function setClientId(string $clientId): void;

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void;
}
