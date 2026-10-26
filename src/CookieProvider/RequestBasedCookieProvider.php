<?php

declare(strict_types=1);

namespace Setono\ClientBundle\CookieProvider;

use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestBasedCookieProvider implements CookieProviderInterface
{
    private const MAX_CLIENT_ID_LENGTH = 255;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $cookieName,
    ) {
    }

    public function getCookie(?Request $request = null): ?Cookie
    {
        $cookieValue = ($request ?? $this->requestStack->getMainRequest())?->cookies->get($this->cookieName);
        if (!is_string($cookieValue) || '' === $cookieValue) {
            return null;
        }

        try {
            $cookie = Cookie::fromString($cookieValue);
        } catch (\InvalidArgumentException) {
            return null;
        }

        // All visitors with an empty client id would share one client, and the database column holds 255 characters
        if ('' === $cookie->clientId || strlen($cookie->clientId) > self::MAX_CLIENT_ID_LENGTH) {
            return null;
        }

        return $cookie;
    }
}
