<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\EventSubscriber;

use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Like an application that sends analytics after the response, this reads the current client while kernel.terminate
 * runs, when the request has the record_client_on_terminate query parameter
 */
final class RecordClientOnTerminateSubscriber implements EventSubscriberInterface
{
    public ?string $clientId = null;

    public function __construct(private readonly ClientContextInterface $clientContext)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'record',
        ];
    }

    public function record(TerminateEvent $event): void
    {
        if ($event->getRequest()->query->has('record_client_on_terminate')) {
            $this->clientId = $this->clientContext->getClient()->id;
        }
    }
}
