<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataPersister;

use Setono\Client\Client;

interface MetadataPersisterInterface
{
    /**
     * Will persist the metadata for the given client
     */
    public function persist(Client $client): void;
}
