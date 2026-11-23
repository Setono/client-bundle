<?php

declare(strict_types=1);

namespace Setono\ClientBundle\EventSubscriber;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\ClientBundle\MetadataStore\MetadataStoreInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class StoreMetadataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientContextInterface $clientContext,
        private readonly MetadataStoreInterface $metadataStore,
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

        $this->metadataStore->store($this->clientContext->getClient());
    }
}
