<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreStoreCookieEvent
{
    /**
     * If you don't want to store the cookie, set this to false
     */
    public bool $store = true;

    public function __construct(
        public readonly Request $request,
        public readonly Response $response,
    ) {
    }
}
