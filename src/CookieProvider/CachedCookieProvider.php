<?php

declare(strict_types=1);

namespace Setono\ClientBundle\CookieProvider;

use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CachedCookieProvider implements CookieProviderInterface
{
    /** @var array<string, Cookie|null> */
    private array $cache = [];

    public function __construct(
        private readonly CookieProviderInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCookie(Request $request = null): ?Cookie
    {
        $request = $request ?? $this->requestStack->getMainRequest();
        if (null === $request) {
            return null;
        }

        $hash = spl_object_hash($request);

        if (!array_key_exists($hash, $this->cache)) {
            $this->cache[$hash] = $this->decorated->getCookie($request);
        }

        return $this->cache[$hash];
    }
}
