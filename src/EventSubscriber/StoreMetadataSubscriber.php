<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Setono\ClientBundle\MetadataPersister\MetadataPersisterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// todo should be abstracted to a 'metadata persister' or something like that
final class StoreMetadataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientFactoryInterface $clientFactory,
        private readonly MetadataPersisterInterface $metadataPersister,
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

        $this->metadataPersister->persist($this->clientFactory->create());
    }
}
