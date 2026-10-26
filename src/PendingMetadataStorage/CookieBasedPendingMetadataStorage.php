<?php

declare(strict_types=1);

namespace Setono\ClientBundle\PendingMetadataStorage;

use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores the pending metadata in its own cookie as signed JSON. The signature binds the metadata to the client id,
 * so a client can neither forge metadata nor reuse the pending metadata of another client
 */
final class CookieBasedPendingMetadataStorage implements PendingMetadataStorageInterface
{
    /**
     * Browsers allow 4 KB per cookie, but all cookies share the request header limit of the web server (8 KB in nginx
     * and Apache by default). Metadata that does not fit within this limit is persisted right away instead
     */
    private const MAX_VALUE_LENGTH = 2048;

    private const SIGNATURE_CONTEXT = 'setono_client_pending_metadata';

    public function __construct(
        private readonly string $cookieName,
        private readonly string $cookieExpiration,
        private readonly string $secret,
    ) {
    }

    public function get(Request $request, string $clientId): ?array
    {
        $value = $request->cookies->get($this->cookieName);
        if (!is_string($value)) {
            return null;
        }

        $position = strrpos($value, '.');
        if (false === $position) {
            return null;
        }

        $json = substr($value, 0, $position);
        if (!hash_equals($this->sign($clientId, $json), substr($value, $position + 1))) {
            return null;
        }

        /** @var array<string, mixed>|scalar|null $metadata */
        $metadata = json_decode($json, true);

        // The signature proves that store() encoded the metadata, so this only fails for someone who knows the secret.
        // Invalid JSON decodes to null too
        if (!is_array($metadata)) {
            return null;
        }

        return $metadata;
    }

    public function store(Request $request, Response $response, string $clientId, array $metadata): bool
    {
        try {
            // Like Doctrine's json type, this preserves the zero fraction of floats, so the metadata is the same whether it
            // comes from the cookie or the database. Unescaped slashes and unicode keep the URL encoded cookie small
            $json = json_encode(
                $metadata,
                \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            );
        } catch (\JsonException) {
            return false;
        }

        $value = $json . '.' . $this->sign($clientId, $json);

        // the cookie value is URL encoded when sent
        if (strlen(rawurlencode($value)) > self::MAX_VALUE_LENGTH) {
            return false;
        }

        $response->headers->setCookie(HttpCookie::create(
            $this->cookieName,
            $value,
            $this->cookieExpiration,
            secure: $request->isSecure(),
        ));

        return true;
    }

    public function remove(Request $request, Response $response): void
    {
        if ($request->cookies->has($this->cookieName)) {
            $response->headers->clearCookie($this->cookieName);
        }
    }

    private function sign(string $clientId, string $json): string
    {
        return hash_hmac('sha256', self::SIGNATURE_CONTEXT . '.' . $clientId . '.' . $json, $this->secret);
    }
}
