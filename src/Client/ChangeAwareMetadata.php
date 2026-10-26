<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Client;

use Setono\Client\Metadata;

class ChangeAwareMetadata extends Metadata
{
    private bool $dirty = false;

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /**
     * Use this when the metadata holds changes that were not made through this object, e.g. pending metadata of a new client
     */
    public function markDirty(): void
    {
        $this->dirty = true;
    }

    /**
     * Use this when the changes must not be persisted, e.g. because they were stored as pending metadata of a new client
     */
    public function markClean(): void
    {
        $this->dirty = false;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        parent::set($key, $value, $ttl);

        $this->dirty = true;
    }

    public function remove(string $key): void
    {
        parent::remove($key);

        $this->dirty = true;
    }
}
