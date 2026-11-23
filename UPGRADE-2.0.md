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

  The bundle also ignores a client cookie whose client id is empty, longer than 255 bytes or not UTF-8.
- `Setono\ClientBundle\Context\DefaultClientContext`. `CookieBasedClientContext` now gives a request without a valid client cookie a new client itself.
- The dependency on `symfony/service-contracts`.

### Changed

- `setono_client.client_context.default` is now an alias of `setono_client.client_context.cookie_based`, and it no longer includes `CachedClientContext`, which decorates `ClientContextInterface` instead. Inject `ClientContextInterface`: a service that injects `setono_client.client_context.default` would give a request without a client cookie a new client on every call, and none of them would match the client cookie.
- `CookieBasedClientContext` no longer decorates another client context.
- `CachedClientContext` no longer implements `Symfony\Contracts\Service\ResetInterface`, and it is no longer tagged `kernel.reset`. It keeps the client per request instead, and listens to `kernel.terminate` to keep it for that event's listeners.
- The constructors of `CookieBasedClientContext`, `CachedClientContext` and `StoreCookieSubscriber` changed. The bundle's service definitions wire them.
- The client cookie always holds the client id of the current client, i.e. what `ClientContextInterface::getClient()` returns, decorators included. In 1.x, a request with a client cookie kept the cookie's client id. When the ids differ, the client cookie gets the current client's id with fresh first and last seen timestamps.
- Storing the client cookie throws a `LogicException` when the current client's id is empty, longer than 255 bytes or not UTF-8, because the next request couldn't read it back.
- Outside a request (console commands, workers), every `getClient()` call returns an anonymous client of its own. In 1.x, the first one was kept until the container was reset. Listeners of `kernel.terminate` still get the request's current client, as in 1.x.

### Decorating the client context

Decorate `Setono\ClientBundle\Context\ClientContextInterface`, e.g. with `#[AsDecorator(ClientContextInterface::class)]`, or `setono_client.client_context.default`. You no longer need to pick a priority or implement `ResetInterface`. Don't decorate `setono_client.client_context.cached` itself, because Symfony would move its `kernel.terminate` listener to your decorator. `CachedClientContext` decorates the interface with priority `-1024`, so it wraps your decorator, and a request gets one client even if your decorator creates a client on every call.

## Metadata entity mapping

The bundle decides when the container compiles whether its `Setono\ClientBundle\Entity\Metadata` class is an entity or a mapped superclass. It's an entity when it's the configured `metadata_class` (the default), and a mapped superclass for any other class.

### Removed

- `Setono\ClientBundle\EventListener\Doctrine\ConvertToEntityListener`, with the service `setono_client.event_listener_doctrine.convert_to_entity`. The bundle no longer listens to Doctrine's `loadClassMetadata`.

### Changed

- With a `metadata_class` that only implements `MetadataInterface`, without extending `Setono\ClientBundle\Entity\Metadata`, the bundle no longer maps the table `setono_client__metadata`, so your next schema diff or migration drops it. With a custom metadata class configured, the bundle never wrote to that table.

## Metadata change tracking

The bundle requires setono/client `^1.2`, whose `Setono\Client\Metadata` tracks its own changes with `hasChanged()`. The bundle's metadata store stores metadata only when it changed, whatever `Metadata` your client context creates. Setting a key to the value and expiry it already has is not a change, so it no longer writes the row.

### Removed

- `Setono\ClientBundle\Client\ChangeAwareMetadata`. Create a `Setono\Client\Metadata` instead, and call `hasChanged()` where you called `isDirty()`.

### Changed

- `Setono\ClientBundle\Client\LazyChangeAwareMetadata` is now `Setono\ClientBundle\Client\LazyMetadata`. It's internal and final.
- Metadata that your client context creates, e.g. a plain `Setono\Client\Metadata`, is no longer stored on every request, only when it changed.
- In setono/client 1.2, `$client->metadata->set($key, $value)` without a TTL removes any expiry the key had, so the key never expires. With setono/client 1.1, the key kept its expiry. To keep a key expiring, pass the TTL again: `set($key, $value, $ttl)`.

## Metadata store

One service loads and stores the metadata of clients: `Setono\ClientBundle\MetadataStore\MetadataStoreInterface`. It replaces the metadata provider and the metadata persister. The bundle's `DoctrineOrmBasedMetadataStore` is `setono_client.metadata_store.doctrine_orm_based`, which `setono_client.metadata_store.default` aliases, and the interface aliases `setono_client.metadata_store.default`.

### Removed

- `Setono\ClientBundle\MetadataProvider\MetadataProviderInterface`, `EmptyMetadataProvider` and `DoctrineOrmBasedMetadataProvider`, with the services `setono_client.metadata_provider.default`, `setono_client.metadata_provider.empty` and `setono_client.metadata_provider.doctrine_orm_based`.
- `Setono\ClientBundle\MetadataPersister\MetadataPersisterInterface` and `DoctrineOrmBasedMetadataPersister`, with the services `setono_client.metadata_persister.default` and `setono_client.metadata_persister.doctrine_orm_based`.
- The fallback to a decorated metadata provider, with no replacement. `DoctrineOrmBasedMetadataProvider` asked the provider it decorated for the metadata of a client without a stored row. Now a client without a stored row gets empty metadata. A decorator of the store can't take its place: it can't tell whether a client has a stored row without loading the metadata, which would query the database on every request.

### Changed

- The constructors of `CookieBasedClientContext` and `StoreMetadataSubscriber` take a `MetadataStoreInterface`. The bundle's service definitions inject the interface into both, so a decorator of the store loads and stores the metadata of every request.

### Porting a custom provider or persister

Implement `MetadataStoreInterface`, which does both, and decorate `Setono\ClientBundle\MetadataStore\MetadataStoreInterface`, e.g. with `#[AsDecorator(MetadataStoreInterface::class)]`, or `setono_client.metadata_store.default`. The bundle calls `load()` on every request with a client cookie and `store()` at the end of every main request, so an implementation must keep the two rules of the interface:

- `load(string $clientId): Metadata` takes the place of `MetadataProviderInterface::getMetadata()`. It returns metadata that doesn't touch your storage until it's used, e.g. a lazy ghost object.
- `store(Client $client): void` takes the place of `MetadataPersisterInterface::persist()`. It stores the metadata only when it changed (`$client->metadata->hasChanged()`), and it doesn't load metadata that was never used to find out.

A decorator that ports only a provider passes `store()` on to the decorated store. A decorator that ports a persister, or adds to the storing, e.g. by also writing changed metadata elsewhere, ports the loading too. The bundle's store keeps to itself whether the metadata it loaded was used, so calling `hasChanged()` on that metadata loads it, which queries the database on every request with a client cookie.