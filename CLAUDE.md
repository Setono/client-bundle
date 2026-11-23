# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Symfony bundle (`setono/client-bundle`) that integrates the `setono/client` library into a Symfony app to **track users between visits**. It stores a cookie (`setono_client_id` by default) holding a client id plus first/last-seen timestamps, and optionally persists per-client key/value **metadata** to a database table. Metadata is lazy: nothing is queried or written unless the application actually reads or mutates it.

This is a library/bundle — there is no runnable application here. It is exercised through its test suite and consumed by host Symfony apps.

## Commands

PHP 8.1+ is required (CI runs 8.1 and 8.2). Use the `8.1`/`8.2` shell switchers if needed. The dev tooling is the individually-pinned set from `setono/code-quality-pack` (inlined into `require-dev` rather than depending on the meta-package); PHPUnit is held at `^10.5` so PHP 8.1 stays supported (PHPUnit 11 needs 8.2).

- Tests: `composer phpunit` (or `vendor/bin/phpunit`) runs both suites; pick one with `--testsuite unit` or `--testsuite functional`
  - `tests/Unit`: isolated classes with Prophecy doubles.
  - `tests/Functional`: the bundle inside a real Symfony app (`tests/Functional/App/Kernel.php`: FrameworkBundle + DoctrineBundle + DAMA DoctrineTestBundle + this bundle), driven over HTTP with `KernelBrowser` against a SQLite database. `tests/bootstrap.php` creates the schema once per PHPUnit process, and DAMA's PHPUnit extension (registered in `phpunit.xml.dist`) runs every test in a transaction that it rolls back. So tests must not run DDL: MySQL commits a transaction on DDL. To check which queries a request ran, read DoctrineBundle's debug data holder (see `MetadataTest::getExecutedQueries()`). Extend `FunctionalTestCase`, which rethrows exceptions instead of rendering error pages and has helpers to read the stored metadata. Every request gets a new container, like PHP-FPM. The kernel always runs in the `test` environment with debug on, whatever `APP_ENV`/`APP_DEBUG` say (the Symfony CLI's `php` sets `APP_ENV=dev`). A test class can pick another environment by overriding `getEnvironment()`, like `CustomMetadataClassTest`. Every environment needs its schema created in `tests/bootstrap.php`, and one that maps the entities differently also needs its own DAMA connection key and no static metadata cache (see the `metadata_class` environments in the kernel, `custom_metadata` and `interface_only_metadata`, each mapping its own entity from `tests/Functional/App/config/doctrine/<environment>/`). Its cache, log and database live in `sys_get_temp_dir()/setono-client-bundle-<hash of the checkout><TEST_TOKEN>/`, so nothing is generated in the repo, and neither two checkouts nor parallel Infection processes share a container or a database. The bootstrap also removes the cached container, because Infection swaps in mutated code without changing file times. Set `SETONO_CLIENT_BUNDLE_DATABASE_URL` (not `DATABASE_URL`, on purpose) to run against another database: the bootstrap drops the bundle's tables in it, and all Infection processes share it, so run Infection with `--threads=1` then.
- Single test: `vendor/bin/phpunit --filter after_loading_the_correct_parameter_has_been_set` or by path `vendor/bin/phpunit tests/Unit/DependencyInjection/SetonoClientExtensionTest.php`
- Static analysis: `composer analyse` (PHPStan at `level: max`, config in `phpstan.neon.dist`; auto-loads the doctrine/symfony/phpunit/strict-rules extensions via `phpstan/extension-installer`)
- Check style: `composer check-style` (ECS, Sylius Labs ruleset)
- Fix style: `composer fix-style`
- Mutation testing: `composer infection` (config in `infection.json.dist`; needs a coverage driver — pcov/xdebug). CI gates on `--min-msi=100 --min-covered-msi=100`, and the config ignores no mutants.
- Dependency analysis: `vendor/bin/composer-dependency-analyser` (no composer script; see `composer-dependency-analyser.php`)
- Rector (dry-run): `vendor/bin/rector process --dry-run` (config in `rector.php`, `withPhpSets(php81: true)`; not run in CI)

CI (`.github/workflows/build.yaml`) additionally runs `composer validate --strict` and `composer normalize --dry-run` across the PHP 8.1/8.2 × Symfony 6.4/7.0 matrix.

Branches: `2.x` (the default) is the next major and may break BC; `1.x` is the 1.x maintenance line. The Roave BC check (`.github/workflows/backwards-compatibility-check.yaml`) runs on PRs into `1.x` and skips PRs into `2.x` until 2.0.0 is released.

> Local note (PHP 8.4): the transitive dev dep `thecodingmachine/safe` emits a flood of implicit-nullable `E_DEPRECATED` notices on PHP 8.4 (they don't exist on CI's 8.1/8.2). This is harmless for PHPStan/PHPUnit but breaks Infection's initial test run — run it with `vendor/bin/infection --initial-tests-php-options="-d error_reporting=24575"` locally on 8.4.

## Architecture

The core abstraction is `ClientContextInterface::getClient(): Client`. Controllers obtain the current `Setono\Client\Client` either by autowiring `ClientContextInterface`, or — more commonly — by type-hinting `Setono\Client\Client` directly on a controller argument, which `Controller/ClientValueResolver` resolves via the context.

### Service wiring (decoration)

The client context is built with **Symfony service decoration** in `config/services.xml`. Higher `decoration-priority` is applied first and ends up *innermost*; the decorated id resolves to the outermost decorator. Understanding the resolution order is essential before changing any service wiring:

- **Client context**: runtime order outermost→innermost: `CachedClientContext` → the application's decorators → `CookieBasedClientContext` (`setono_client.client_context.cookie_based`, aliased by `setono_client.client_context.default`, which the interface alias points to). The current client is the source of truth for the client id; see `docs/adr/0001-the-current-client-is-the-source-of-truth-for-the-client-id.md`.
  - `CachedClientContext` decorates the **interface alias** `ClientContextInterface` at priority `-1024`, so it wraps every application decorator, whether that decorates the interface (e.g. `#[AsDecorator(ClientContextInterface::class)]`) or `setono_client.client_context.default`. It gives a request one client, even when a decorator creates a client on every call. `tests/Functional/DecoratedClientContextTest.php` covers both styles with such decorators (`tests/Functional/App/Context/QueryParameterClientContext.php` and `HeaderClientContext.php`). It keeps the clients in a `WeakMap` keyed by the main `Request` object, so it needs no `kernel.reset` in long-running runtimes (FrankenPHP worker mode, RoadRunner; `tests/Functional/LongRunningProcessTest.php`). Don't key by `spl_object_hash()`: PHP hands a freed request's handle to the next request. Without a request it passes every call on. Any new service that holds per-request state should key it by the request object the same way.
  - Injecting `setono_client.client_context.default` skips `CachedClientContext`, which decorates the interface alias, so a request without a cookie would get a new client on every call. Inject the interface. The bundle's own consumers (`StoreCookieSubscriber`, `StoreMetadataSubscriber`, `ClientValueResolver`) do.
  - `CookieBasedClientContext` reads the client cookie of the main request with `Cookie/ClientCookieJar`. A valid cookie gives `Client(cookieId, metadataStore->load(...))`; otherwise it's a new client, `new Client()`, with empty metadata. Without a request, every call gets an anonymous client that is never stored.
  - `CachedClientContext` is also a `kernel.terminate` subscriber. Symfony pops the request before it dispatches `kernel.terminate`, so a listener at priority 2048 keeps the request being terminated, through a `WeakReference`, and one at -2048 drops it. `CachedClientContext` uses that request as the key while the stack is empty, so terminate listeners get the request's current client (`tests/Functional/KernelTerminateTest.php`). They get it from the cache: `StoreMetadataSubscriber` asks for the current client on `kernel.finish_request` of every main request, before the pop. `CookieBasedClientContext` only reads the `RequestStack`, so a cache miss during terminate would give an anonymous client.
  - Because of that subscriber tag, don't decorate `setono_client.client_context.cached` itself: Symfony moves a decorated service's tags to its decorator, so the container fails to compile ("must implement EventSubscriberInterface"). Decorate the interface or `setono_client.client_context.default`.
- **Metadata store**: one seam, `MetadataStore/MetadataStoreInterface` (`load()` and `store()`), with no decoration in the bundle: the interface aliases `setono_client.metadata_store.default`, which aliases `setono_client.metadata_store.doctrine_orm_based`. `CookieBasedClientContext` and `StoreMetadataSubscriber` inject the interface, so an app's decorator of the interface or of `.default` handles both loading and storing (`tests/Functional/DecoratedMetadataStoreTest.php` with `tests/Functional/App/MetadataStore/TraceableMetadataStore.php`).

`Cookie/ClientCookieJar` (`@internal`) owns everything about the client cookie: its name, format, validation (a non-empty UTF-8 client id of at most 255 bytes) and expiry. `read()` returns null for a missing or invalid cookie. `write()` stores the current client's id, keeps `firstSeenAt` only when the request's cookie holds the same id (otherwise fresh timestamps), and throws a `LogicException` for a client id that `read()` would reject.

### Request lifecycle

- `EventSubscriber/StoreCookieSubscriber` (on `kernel.response`): dispatches `Event/PreStoreCookieEvent` (an app can set `$store = false` to veto), then writes the client cookie of the current client with `ClientCookieJar::write()`. The cookie is set `HttpOnly(false)` on purpose so client-side JS can read it.
- `EventSubscriber/StoreMetadataSubscriber` (on `kernel.finish_request`): calls `MetadataStoreInterface::store()` with the current client.

### Lazy metadata (why writes are cheap)

`setono/client`'s `Metadata` tracks its own changes: `hasChanged()` turns true once a call alters a value or an expiry, including dropping an expired key, and never resets (`docs/adr/0002-metadata-tracks-its-own-changes-in-setono-client.md`). The interface docblock makes two rules the contract of every store: `load()` doesn't touch storage until the metadata is used, and `store()` stores only changed metadata. `MetadataStore/DoctrineOrmBasedMetadataStore` keeps them. `load()` returns a `Client/LazyMetadata` ghost (`@internal`, used only by the store; it only adds Symfony VarExporter's `LazyGhostTrait`) whose initializer looks up the entity manager and queries on first access. A client without a row gets empty metadata, with no fallback (`UPGRADE-2.0.md`). `store()` **short-circuits** in two cases: the metadata is a lazy ghost that was never initialized (never used), or it hasn't changed. It checks the ghost first, because calling `hasChanged()` on a ghost loads it, and it looks up the entity manager only after both checks. This is what makes "lazy loaded metadata" in the README real: no SELECT, no flush, and no entity manager unless the app touched metadata. `MetadataTest::hasStartedAnEntityManager()` checks the last one: it asks the container whether any manager in `ManagerRegistry::getManagerNames()` is initialized. The store has functional tests on SQLite only, no Prophecy doubles of the entity manager.

Doctrine itself skips the UPDATE when the stored JSON decodes to an identical array, so on SQLite a flush of unchanged metadata writes nothing and a test can't tell it happened. MySQL reorders the keys of a JSON column, so there it would write. A functional test that checks for a missing write stores the row with its keys in another order first, like `MetadataTest::it_does_not_store_metadata_that_was_set_to_the_value_it_already_has`.

### Entity mapping

`Entity/Metadata` (string id `clientId`, nullable `json` `metadata`) has two XML mappings, and the container decides at compile time which one Doctrine gets. `config/doctrine-mapping/entity/` maps it as an entity on table `setono_client__metadata`; `config/doctrine-mapping/mapped-superclass/` maps it as a mapped superclass without a table. `SetonoClientBundle::build()` registers each with its own `DoctrineOrmMappingsPass`, enabled by the parameter `setono_client.doctrine_mapping.entity` or `setono_client.doctrine_mapping.mapped_superclass`; the pass checks only that its parameter exists, not its value. `SetonoClientExtension::load()` sets exactly one: the entity mapping when `metadata_class` is the bundle's `Metadata` (the default), the mapped superclass for any other class. Apps swap in a custom class via the `metadata_class` config option; the extension enforces that it implements `Entity/MetadataInterface`, stores its declared name (e.g. without a leading backslash) in the parameter, and the app maps it. A subclass of `Entity/Metadata` inherits its fields (`tests/Functional/CustomMetadataClassTest.php` runs `MetadataTest` that way); a class that only implements the interface leaves no `setono_client__metadata` table in the schema (`InterfaceOnlyMetadataClassTest`, the same way).

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

- Strict types everywhere (`declare(strict_types=1)`); concrete classes are `final`, except `Entity\Metadata`, which applications extend as their `metadata_class`.
- New services go in `config/services.xml`. A service behind a public interface uses the `setono_client.<concern>.<variant>` id scheme, with a `setono_client.<concern>.default` alias and an interface alias for autowiring. Any other service (a subscriber, the value resolver, an `@internal` class) is `setono_client.<namespace>.<name>`, like `setono_client.event_subscriber.store_cookie` and `setono_client.cookie.client_cookie_jar`, without aliases. To change a service's behavior, decorate it rather than editing existing classes, and pick a `decoration-priority` only where the order among decorators matters (`CachedClientContext`). To change the current client, decorate `ClientContextInterface`; `CachedClientContext` stays outermost on its own.
- Tests use `matthiasnoback/symfony-dependency-injection-test` (e.g. `AbstractExtensionTestCase`), `matthiasnoback/symfony-config-test` (`ConfigurationTestCaseTrait`) and Prophecy (`ProphecyTrait`), with the `@test` annotation and snake_case method-name style. Keep the suite at 100% MSI — when adding logic, add a test that kills the corresponding mutant.

## Agent skills

- When grilling (`/mattpocock-skills:grilling`, `/mattpocock-skills:grill-me`, `/mattpocock-skills:grill-with-docs`, and grilling inside other skills), ask via the `AskUserQuestion` tool instead of as plain chat text. The skills ask in chat on purpose and leave this to the project's CLAUDE.md ([mattpocock/skills: native question tool](https://github.com/mattpocock/skills/blob/main/.out-of-scope/native-question-tool.md)).

### Issue tracker

Issues live in GitHub Issues for `Setono/client-bundle`, managed with the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default labels: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. Each label is named after its role. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and one `docs/adr/` at the repo root. See `docs/agents/domain.md`.
