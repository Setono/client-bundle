<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Entity;

interface ClientInterface
{
    public function getId(): ?string;

    public function setId(string $id): void;

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void;
}
