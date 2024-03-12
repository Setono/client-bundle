<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Entity;

class Client implements ClientInterface
{
    protected ?string $id = null;

    /** @var array<string, mixed> */
    protected array $metadata = [];

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
