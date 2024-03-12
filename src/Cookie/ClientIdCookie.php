<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Cookie;

use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

final class ClientIdCookie implements \Stringable
{
    public readonly int $createdAt;

    public readonly int $updatedAt;

    public function __construct(
        public readonly string $clientId,
        int $createdAt = null,
        int $updatedAt = null,
    ) {
        $this->createdAt = $createdAt ?? time();
        $this->updatedAt = $updatedAt ?? time();
    }

    /**
     * @throws \InvalidArgumentException if the cookie is not valid
     */
    public static function fromString(string $cookie): self
    {
        $parts = explode('.', $cookie, 3);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('The cookie is not valid');
        }

        $createdAt = $parts[0];
        if (!is_numeric($createdAt)) {
            throw new \InvalidArgumentException('The created at part of the cookie is not valid');
        }
        $createdAt = (int) $createdAt;

        $updatedAt = $parts[1];
        if (!is_numeric($updatedAt)) {
            throw new \InvalidArgumentException('The updated at part of the cookie is not valid');
        }
        $updatedAt = (int) $updatedAt;

        $clientId = $parts[2];

        return new self($clientId, $createdAt, $updatedAt);
    }

    public function asHttpCookie(string $name, string $expiresAt): HttpCookie
    {
        return HttpCookie::create($name, $this->toString(), $expiresAt);
    }

    public function toString(): string
    {
        return sprintf('%d.%d.%s', $this->createdAt, $this->updatedAt, $this->clientId);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
