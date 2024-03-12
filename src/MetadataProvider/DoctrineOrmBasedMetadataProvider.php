<?php

declare(strict_types=1);

namespace Setono\ClientBundle\MetadataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Metadata;
use Setono\ClientBundle\Entity\Client;

final class DoctrineOrmBasedMetadataProvider implements MetadataProviderInterface
{
    public function __construct(
        private readonly MetadataProviderInterface $decorated,
        private readonly ManagerRegistry $managerRegistry,
        /**
         * @var class-string<Client> $clientClass
         */
        private readonly string $clientClass,
    ) {
    }

    public function getMetadata(string $clientId): Metadata
    {
        $manager = $this->managerRegistry->getManagerForClass($this->clientClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->clientClass));
        }

        $client = $manager->find($this->clientClass, $clientId);

        if (null === $client) {
            return $this->decorated->getMetadata($clientId);
        }

        return new Metadata($client->getMetadata());
    }
}
