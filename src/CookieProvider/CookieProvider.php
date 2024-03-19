<?php

declare(strict_types=1);

namespace Setono\ClientBundle\CookieProvider;

use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieProvider implements CookieProviderInterface
{
    /** @var array<string, Cookie|null> */
    private array $cache = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $cookieName,
    ) {
    }

    public function getClientCookie(Request $request = null): ?Cookie
    {
        $request = $request ?? $this->requestStack->getMainRequest();
        if (null === $request) {
            return null;
        }

        $hash = spl_object_hash($request);

        if (!array_key_exists($hash, $this->cache)) {
            $this->cache[$hash] = $this->getCookie($request);
        }

        return $this->cache[$hash];
    }

    private function getCookie(Request $request): ?Cookie
    {
        $cookieValue = $request->cookies->get($this->cookieName);
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
