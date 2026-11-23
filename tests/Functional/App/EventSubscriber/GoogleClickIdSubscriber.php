<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\EventSubscriber;

use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Stores the Google click id on the response, like Setono/SyliusGoogleAdsPlugin does. The gclid query parameter is stored
 * before the bundle stores its cookie (priority -64), and the late_gclid query parameter after it
 */
final class GoogleClickIdSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ClientContextInterface $clientContext)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [
                ['storeGoogleClickId'],
                ['storeLateGoogleClickId', -128],
            ],
        ];
    }

    public function storeGoogleClickId(ResponseEvent $event): void
    {
        $this->store($event, 'gclid');
    }

    public function storeLateGoogleClickId(ResponseEvent $event): void
    {
        $this->store($event, 'late_gclid');
    }

    private function store(ResponseEvent $event, string $parameter): void
    {
        $gclid = $event->getRequest()->query->get($parameter);
        if (null !== $gclid) {
            $this->clientContext->getClient()->metadata->set('gclid', $gclid, 7_776_000);
        }
    }
}
