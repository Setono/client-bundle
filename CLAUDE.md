# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Symfony bundle (`setono/client-bundle`) that integrates the `setono/client` library into a Symfony app to **track users between visits**. It stores a cookie (`setono_client_id` by default) holding a client id plus first/last-seen timestamps, and optionally persists per-client key/value **metadata** to a database table. Metadata is lazy: nothing is queried or written unless the application actually reads or mutates it.

This is a library/bundle — there is no runnable application here. It is exercised through its test suite and consumed by host Symfony apps.

## Commands

PHP 8.1+ is required (CI runs 8.1 and 8.2). Use the `8.1`/`8.2` shell switchers if needed. The dev tooling is the individually-pinned set from `setono/code-quality-pack` (inlined into `require-dev` rather than depending on the meta-package); PHPUnit is held at `^10.5` so PHP 8.1 stays supported (PHPUnit 11 needs 8.2).

- Tests: `composer phpunit` (or `vendor/bin/phpunit`)
- Single test: `vendor/bin/phpunit --filter after_loading_the_correct_parameter_has_been_set` or by path `vendor/bin/phpunit tests/DependencyInjection/SetonoClientExtensionTest.php`
- Static analysis: `composer analyse` (PHPStan at `level: max`, config in `phpstan.neon.dist`; auto-loads the doctrine/symfony/phpunit/strict-rules extensions via `phpstan/extension-installer`)
- Check style: `composer check-style` (ECS, Sylius Labs ruleset)
- Fix style: `composer fix-style`
- Mutation testing: `composer infection` (config in `infection.json.dist`; needs a coverage driver — pcov/xdebug). CI gates on `--min-msi=100 --min-covered-msi=100`. One equivalent mutant (the defensive `instanceof ClassMetadata` in `ConvertToEntityListener`) is excluded in the config.
- Dependency analysis: `vendor/bin/composer-dependency-analyser` (no composer script; see `composer-dependency-analyser.php`)
- Rector (dry-run): `vendor/bin/rector process --dry-run` (config in `rector.php`, `withPhpSets(php81: true)`; not run in CI)

CI (`.github/workflows/build.yaml`) additionally runs `composer validate --strict` and `composer normalize --dry-run` across the PHP 8.1/8.2 × Symfony 6.4/7.0 matrix.

> Local note (PHP 8.4): the transitive dev dep `thecodingmachine/safe` emits a flood of implicit-nullable `E_DEPRECATED` notices on PHP 8.4 (they don't exist on CI's 8.1/8.2). This is harmless for PHPStan/PHPUnit but breaks Infection's initial test run — run it with `vendor/bin/infection --initial-tests-php-options="-d error_reporting=24575"` locally on 8.4.

## Architecture

The core abstraction is `ClientContextInterface::getClient(): Client`. Controllers obtain the current `Setono\Client\Client` either by autowiring `ClientContextInterface`, or — more commonly — by type-hinting `Setono\Client\Client` directly on a controller argument, which `Controller/ClientValueResolver` resolves via the context.

### Decorator chains (the key structural idea)

Three concerns are each built as a **Symfony service-decoration chain** in `config/services.xml`. Higher `decoration-priority` is applied first and ends up *innermost*; the public alias resolves to the outermost decorator. Understanding the resolution order is essential before changing any service wiring:

- **Client context** — runtime order outermost→innermost: `CachedClientContext` (prio 32) → `CookieBasedClientContext` (prio 64) → `DefaultClientContext`.
  - `Cached` memoizes the `Client` for the request.
  - `CookieBased` reads the cookie; if present, builds `Client(cookieId, metadataProvider->getMetadata(...))`; otherwise delegates.
  - `Default` returns an anonymous `new Client(null, new ChangeAwareMetadata())`.
- **Cookie provider** — `CachedCookieProvider` (prio 32, memoizes per `Request` via `spl_object_hash`) → `RequestBasedCookieProvider` (parses the cookie off the request).
- **Metadata provider** — `DoctrineOrmBasedMetadataProvider` (prio 64) → `EmptyMetadataProvider` (default). Doctrine loads from DB, falling back to the decorated provider when no row exists.

### Request lifecycle

- `EventSubscriber/StoreCookieSubscriber` (on `kernel.response`): dispatches `Event/PreStoreCookieEvent` (an app can set `$store = false` to veto), then writes/refreshes the cookie with an updated `lastSeenAt`. The cookie is set `HttpOnly(false)` on purpose so client-side JS can read it.
- `EventSubscriber/StoreMetadataSubscriber` (on `kernel.finish_request`): calls the metadata persister for the current client.

### Lazy metadata (why writes are cheap)

`Client/ChangeAwareMetadata` extends `setono/client`'s `Metadata` and flips a `dirty` flag on `set`/`remove`. `Client/LazyChangeAwareMetadata` adds Symfony VarExporter's `LazyGhostTrait`, so `DoctrineOrmBasedMetadataProvider` returns a lazy ghost whose DB query only fires on first access. `DoctrineOrmBasedMetadataPersister::persist()` then **short-circuits** in two cases: the metadata is a lazy ghost that was never initialized (never read), or it is not dirty (never mutated). This is what makes "lazy loaded metadata" in the README real — no SELECT and no flush unless the app touched metadata.

### Entity mapping

`Entity/Metadata` (table `setono_client__metadata`, string id `clientId`, nullable `json` `metadata`) is mapped in `config/doctrine-mapping/Metadata.orm.xml` as a **mapped-superclass**. `EventListener/Doctrine/ConvertToEntityListener` listens on Doctrine's `loadClassMetadata` and flips `isMappedSuperclass = false` so the bundle's own `Metadata` becomes a concrete entity when the app provides no replacement. Apps can swap in a custom class via the `metadata_class` config option; `SetonoClientExtension::load()` enforces that it implements `Entity/MetadataInterface` and they are responsible for mapping it.

### Configuration

Defined in `DependencyInjection/Configuration.php`, mapped to container parameters in `SetonoClientExtension`:

```yaml
setono_client:
    cookie:
        name: setono_client_id   # changing this makes existing clients appear new
        expiration: '+365 days'  # any strtotime-parsable string
    metadata_class: Setono\ClientBundle\Entity\Metadata
```

## Conventions

- Strict types everywhere (`declare(strict_types=1)`); concrete classes are `final`, except the metadata/entity classes (`ChangeAwareMetadata`, `LazyChangeAwareMetadata`, `Entity\Metadata`) which are intentionally extensible.
- New services go in `config/services.xml` using the `setono_client.<concern>.<variant>` id scheme, with a `setono_client.<concern>.default` alias and an interface alias for autowiring. To insert behavior into a chain, add a decorator with an appropriate `decoration-priority` rather than editing existing classes.
- Tests use `matthiasnoback/symfony-dependency-injection-test` (e.g. `AbstractExtensionTestCase`) and Prophecy (`ProphecyTrait`), with the `@test` annotation and snake_case method-name style. Keep the suite at 100% MSI — when adding logic, add a test that kills the corresponding mutant.
