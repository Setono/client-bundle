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
final class StoreMetadataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $clientFactory,
        private readonly ManagerRegistry $managerRegistry,
        /**
         * @var class-string<MetadataInterface> $metadataClass
         */
        private readonly string $metadataClass,
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
        $manager = $this->managerRegistry->getManagerForClass($this->metadataClass);
        if (null === $manager) {
            throw new \RuntimeException(sprintf('No manager found for class %s', $this->metadataClass));
        }

        $entity = $manager->find($this->metadataClass, $client->id);
        if (null === $entity) {
            /** @var MetadataInterface $entity */
            $entity = new $this->metadataClass();
            $entity->setClientId($client->id);

            $manager->persist($entity);
        }

        $entity->setMetadata($client->metadata->toArray());

        $manager->flush();
    }
}
