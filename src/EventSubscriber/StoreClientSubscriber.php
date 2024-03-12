<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Setono\ClientBundle\Entity\MetadataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// todo should be abstracted to a 'metadata persister' or something like that
final class StoreClientSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $clientFactory,
        private readonly ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataInterface> $clientClass
         */
        private readonly string $clientClass,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::FINISH_REQUEST => 'store',
        ];
    }

    public function store(FinishRequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $client = $this->clientFactory->create();
        $manager = $this->managerRegistry->getManagerForClass($this->clientClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->clientClass));
        }

        $entity = $manager->find($this->clientClass, $client->id);
        if (null === $entity) {
            /** @var MetadataInterface $entity */
            $entity = new $this->clientClass();
            $entity->setClientId($client->id);

            $manager->persist($entity);
        }

        $entity->setMetadata($client->metadata->toArray());

        $manager->flush();
    }
}
