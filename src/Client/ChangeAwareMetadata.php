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

    public function set(string $key, mixed $value): void
    {
        parent::set($key, $value);

        $this->dirty = true;
    }

    public function remove(string $key): void
    {
        parent::remove($key);

        $this->dirty = true;
    }
}
