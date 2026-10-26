<?php

declare(strict_types=1);

namespace Setono\ClientBundle\CookieProvider;

use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CachedCookieProvider implements CookieProviderInterface
{
    /**
     * Keyed by the request object, so an entry goes away with its request, and a new request never gets the cookie
     * of an earlier one. That's why, unlike CachedClientContext, this service needs no reset between requests in
     * long-running runtimes. The cookie is wrapped in an array, because the WeakMap treats an entry holding null as missing
     *
     * @var \WeakMap<Request, array{cookie: Cookie|null}>
     */
    private readonly \WeakMap $cache;

    public function __construct(
        private readonly CookieProviderInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
        $this->cache = new \WeakMap();
    }

    public function getCookie(?Request $request = null): ?Cookie
    {
        $request ??= $this->requestStack->getMainRequest();
        if (null === $request) {
            return null;
        }

        $this->cache[$request] ??= ['cookie' => $this->decorated->getCookie($request)];

        return $this->cache[$request]['cookie'];
    }
}
