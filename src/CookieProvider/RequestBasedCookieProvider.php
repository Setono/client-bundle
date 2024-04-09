<?php

declare(strict_types=1);

namespace Setono\ClientBundle\CookieProvider;

use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestBasedCookieProvider implements CookieProviderInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $cookieName,
    ) {
    }

    public function getCookie(Request $request = null): ?Cookie
    {
        $cookieValue = ($request ?? $this->requestStack->getMainRequest())?->cookies->get($this->cookieName);
        if (!is_string($cookieValue) || '' === $cookieValue) {
            return null;
        }

        try {
            return Cookie::fromString($cookieValue);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
