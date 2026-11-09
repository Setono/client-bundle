<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Cookie;

use Setono\Client\Client;
use Setono\Client\Cookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads and writes the client cookie, which holds the client id and the first and last seen timestamps
 *
 * @internal
 */
final class ClientCookieJar
{
    private const MAX_CLIENT_ID_LENGTH = 255;

    public function __construct(
        private readonly string $name,
        private readonly string $expiration,
    ) {
    }

    /**
     * Returns the client cookie of the request, or null if the request has no valid client cookie
     */
    public function read(Request $request): ?Cookie
    {
        $value = $request->cookies->get($this->name);
        if (!is_string($value) || '' === $value) {
            return null;
        }

        try {
            $cookie = Cookie::fromString($value);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return self::isValid($cookie->clientId) ? $cookie : null;
    }

    /**
     * Sets the client cookie of the given client on the response. When the request's client cookie holds the same
     * client id, the first seen timestamp carries over. Otherwise, the client id is new to this cookie, and so are its timestamps
     *
     * @throws \LogicException if the client id isn't valid, because read() would reject it on the next request
     */
    public function write(Request $request, Response $response, Client $client): void
    {
        if (!self::isValid($client->id)) {
            throw new \LogicException(sprintf(
                'The client id must be a non-empty string of at most %d bytes to be stored in the client cookie, but the current client has a client id of %d bytes. Check the services that decorate the client context (ClientContextInterface)',
                self::MAX_CLIENT_ID_LENGTH,
                strlen($client->id),
            ));
        }

        $cookie = $this->read($request);
        if (null === $cookie || $cookie->clientId !== $client->id) {
            $cookie = new Cookie($client->id);
        }

        $response->headers->setCookie(
            HttpCookie::create($this->name, $cookie->withLastSeenAt(time())->toString(), $this->expiration)
                ->withHttpOnly(false), // this allows javascript to read the cookie
        );
    }

    /**
     * A valid client id isn't empty, because all clients with an empty client id would share one client. It's at most
     * 255 bytes, so it fits the database column, which holds 255 characters
     */
    private static function isValid(string $clientId): bool
    {
        return '' !== $clientId && strlen($clientId) <= self::MAX_CLIENT_ID_LENGTH;
    }
}
