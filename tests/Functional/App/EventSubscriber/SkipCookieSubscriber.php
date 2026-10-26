<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\EventSubscriber;

use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Vetoes the cookie when the request has the skip_cookie query parameter, like an application would do for a visitor
 * that has not consented to cookies
 */
final class SkipCookieSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreStoreCookieEvent::class => 'skipCookie',
        ];
    }

    public function skipCookie(PreStoreCookieEvent $event): void
    {
        if ($event->request->query->has('skip_cookie')) {
            $event->store = false;
        }
    }
}
