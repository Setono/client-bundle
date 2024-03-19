<?php

declare(strict_types=1);

namespace Setono\ClientBundle\CookieProvider;

use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Request;

interface CookieProviderInterface
{
    /**
     * @param Request|null $request if null, the main request will be used
     */
    public function getCookie(Request $request = null): ?Cookie;
}
