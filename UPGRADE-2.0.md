# Upgrade from 1.x to 2.0

## Client resolution

The client context and the cookie are now one module: `ClientContext` recognizes the client, `CachedClientContext` gives a request one client, and an internal `ClientCookieJar` reads and writes the cookie.

### Removed

- `Setono\ClientBundle\CookieProvider\CookieProviderInterface`, `RequestBasedCookieProvider` and `CachedCookieProvider`, with the services `setono_client.cookie_provider.default` and `setono_client.cookie_provider.cached`. To read the cookie's timestamps, parse the cookie of the request yourself:

  ```php
  $cookie = \Setono\Client\Cookie::fromString($request->cookies->getString('setono_client_id')); // throws on an invalid cookie
  ```

- `Setono\ClientBundle\Context\CookieBasedClientContext` and `DefaultClientContext`, with the service `setono_client.client_context.cookie_based`. `Setono\ClientBundle\Context\ClientContext` (`setono_client.client_context.default`) replaces both.
- `CachedClientContext` no longer implements `Symfony\Contracts\Service\ResetInterface` and is no longer tagged `kernel.reset`. Its constructor takes a `RequestStack` as its second argument.
- The dependency on `symfony/service-contracts`.

### Changed

- `StoreCookieSubscriber::__construct()` takes `ClientContextInterface`, `ClientCookieJar` and the event dispatcher. The cookie name and expiration moved to `ClientCookieJar`.
- The cookie always holds the client id of the current client, i.e. what `ClientContextInterface::getClient()` returns, decorators included. In 1.x, a request with a cookie kept the cookie's client id. When the ids differ, the cookie gets the new client id and fresh first and last seen timestamps.
- The cookie writer throws a `LogicException` when the current client's id is empty or longer than 255 bytes, because the next request couldn't read it back.
- Outside a request (console commands, workers), every `getClient()` call returns a new anonymous client. In 1.x, the first one was kept until the container was reset.

### Decorating the client context

Decorate `Setono\ClientBundle\Context\ClientContextInterface`, e.g. with `#[AsDecorator(ClientContextInterface::class)]`. Decorating `setono_client.client_context.default` works too. You no longer need to pick a priority or implement `ResetInterface`. `CachedClientContext` decorates the interface with priority `-1024`, so it wraps your decorator, and a request gets one client even if your decorator creates a client on every call.
