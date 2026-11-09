# Upgrade from 1.x to 2.0

## Current client and client cookie

The current client is now the source of truth for the client id, and the client cookie follows it. `CookieBasedClientContext` recognizes the client by the client cookie, `CachedClientContext` gives a request one client, and the bundle reads and writes the client cookie internally.

### Removed

- `Setono\ClientBundle\CookieProvider\CookieProviderInterface`, `RequestBasedCookieProvider` and `CachedCookieProvider`, with the services `setono_client.cookie_provider.default` and `setono_client.cookie_provider.cached`. To read the client cookie's timestamps, parse the cookie of the request yourself:

  ```php
  use Setono\Client\Cookie;

  $value = $request->cookies->get('setono_client_id'); // or the cookie name you configured
  if (is_string($value) && '' !== $value) {
      try {
          $cookie = Cookie::fromString($value);
          // $cookie->clientId, $cookie->firstSeenAt, $cookie->lastSeenAt
      } catch (\InvalidArgumentException) {
          // not a valid client cookie
      }
  }
  ```

  The bundle also ignores a client cookie whose client id is empty or longer than 255 bytes.
- `Setono\ClientBundle\Context\DefaultClientContext`. `CookieBasedClientContext` now gives a request without a valid client cookie a new client itself.
- The dependency on `symfony/service-contracts`.

### Changed

- `setono_client.client_context.default` is now an alias of `setono_client.client_context.cookie_based`, and it no longer includes `CachedClientContext`, which decorates `ClientContextInterface` instead. Inject `ClientContextInterface`: a service that injects `setono_client.client_context.default` would give a request without a client cookie a new client on every call, and none of them would match the client cookie.
- `CookieBasedClientContext` no longer decorates another client context.
- `CachedClientContext` no longer implements `Symfony\Contracts\Service\ResetInterface`, and it is no longer tagged `kernel.reset`. It keeps the client per request instead, and listens to `kernel.terminate` to keep it for that event's listeners.
- The constructors of `CookieBasedClientContext`, `CachedClientContext` and `StoreCookieSubscriber` changed. The bundle's service definitions wire them.
- The client cookie always holds the client id of the current client, i.e. what `ClientContextInterface::getClient()` returns, decorators included. In 1.x, a request with a client cookie kept the cookie's client id. When the ids differ, the client cookie gets the current client's id with fresh first and last seen timestamps.
- Storing the client cookie throws a `LogicException` when the current client's id is empty or longer than 255 bytes, because the next request couldn't read it back.
- Outside a request (console commands, workers), every `getClient()` call returns an anonymous client of its own. In 1.x, the first one was kept until the container was reset. Listeners of `kernel.terminate` still get the request's current client, as in 1.x.

### Decorating the client context

Decorate `Setono\ClientBundle\Context\ClientContextInterface`, e.g. with `#[AsDecorator(ClientContextInterface::class)]`, or `setono_client.client_context.default`. You no longer need to pick a priority or implement `ResetInterface`. Don't decorate `setono_client.client_context.cached` itself, because Symfony would move its `kernel.terminate` listener to your decorator. `CachedClientContext` decorates the interface with priority `-1024`, so it wraps your decorator, and a request gets one client even if your decorator creates a client on every call.
