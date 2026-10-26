<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\Entity\MetadataInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class ClientController
{
    /**
     * @param class-string<MetadataInterface> $metadataClass
     */
    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly ManagerRegistry $managerRegistry,
        #[Autowire('%setono_client.metadata_class%')]
        private readonly string $metadataClass,
    ) {
    }

    /**
     * Uses the client, but not its metadata
     */
    public function id(): Response
    {
        return new Response($this->clientContext->getClient()->id);
    }

    public function showMetadata(Client $client): JsonResponse
    {
        return new JsonResponse(iterator_to_array($client->metadata));
    }

    public function setMetadata(Request $request, Client $client): Response
    {
        $ttl = $request->query->get('ttl');

        $client->metadata->set(
            $request->query->getString('key'),
            $request->query->getString('value'),
            null === $ttl ? null : (int) $ttl,
        );

        return new Response();
    }

    public function removeMetadata(Request $request, Client $client): Response
    {
        $client->metadata->remove($request->query->getString('key'));

        return new Response();
    }

    /**
     * Sets the metadata like setMetadata() does, but simulates a concurrent request from the same client
     * that inserts the client's row right before this request does
     */
    public function setMetadataConcurrently(Request $request, Client $client): Response
    {
        $client->metadata->set($request->query->getString('key'), $request->query->getString('value'));

        $manager = $this->managerRegistry->getManagerForClass($this->metadataClass);
        Assert::isInstanceOf($manager, EntityManagerInterface::class);

        $manager->getEventManager()->addEventListener(Events::onFlush, new class($manager->getConnection(), $manager->getClassMetadata($this->metadataClass), $client->id) {
            /**
             * @param ClassMetadata<object> $classMetadata
             */
            public function __construct(
                private readonly Connection $connection,
                private readonly ClassMetadata $classMetadata,
                private readonly string $clientId,
            ) {
            }

            public function onFlush(): void
            {
                $this->connection->insert($this->classMetadata->getTableName(), [
                    $this->classMetadata->getColumnName('clientId') => $this->clientId,
                    $this->classMetadata->getColumnName('metadata') => json_encode(['set_by' => 'concurrent request']),
                ]);
            }
        });

        return new Response();
    }
}
