# Client Bundle

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

Recognize returning visitors in your Symfony application and attach your own metadata to each of them.

The bundle assigns every visitor a stable **client id**, stored in a first‑party cookie (`setono_client_id` by
default) that also remembers when the visitor was *first* and *last* seen. On top of that you can persist arbitrary
**metadata** per client — for example the first Google click id (`gclid`) a visitor arrived with, an A/B test variant,
or a referrer. Metadata is **lazy**: if you never read or write it, the bundle never touches the database.

Typical use cases: first‑party analytics, marketing attribution, returning‑visitor personalization.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Get the current client](#get-the-current-client)
  - [Read and write metadata](#read-and-write-metadata)
  - [Set metadata from a request](#set-metadata-from-a-request)
  - [Access the cookie directly](#access-the-cookie-directly)
  - [Skip storing the cookie (consent)](#skip-storing-the-cookie-consent)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [Extending the bundle](#extending-the-bundle)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | Version |
| --- | --- |
| PHP | `>= 8.1` |
| Symfony | `^6.4` or `^7.0` |
| Doctrine ORM | `^2.0` or `^3.0` |

## Installation

```shell
composer require setono/client-bundle
```

If you use [Symfony Flex](https://symfony.com/doc/current/setup/flex.html) the bundle is registered automatically.
Otherwise, enable it manually in `config/bundles.php`:

```php
return [
    // ...
    Setono\ClientBundle\SetonoClientBundle::class => ['all' => true],
];
```

The bundle maps a `Metadata` entity (table `setono_client__metadata`). If you intend to store metadata, create the
table with [Doctrine Migrations](https://symfony.com/bundles/DoctrineMigrationsBundle/current/index.html):

```shell
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

> If you only need the client id cookie and never store metadata, the table is never queried — but generating it now
> keeps things simple if you add metadata later.

## Usage

### Get the current client

The simplest way to access the current visitor is to type‑hint `Setono\Client\Client` in a controller — the bundle
resolves it for you:

```php
use Setono\Client\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class HomeController extends AbstractController
{
    public function index(Client $client): Response
    {
        return $this->render('home.html.twig', [
            'clientId' => $client->id,
        ]);
    }
}
```

Anywhere else (services, subscribers) inject `ClientContextInterface`:

```php
use Setono\ClientBundle\Context\ClientContextInterface;

final class SomeService
{
    public function __construct(private readonly ClientContextInterface $clientContext)
    {
    }

    public function __invoke(): void
    {
        $client = $this->clientContext->getClient();
        // $client->id, $client->metadata
    }
}
```

### Read and write metadata

`$client->metadata` is a key/value store. Reading a key for the first time lazily loads the metadata from the
database; writing marks it dirty so it is persisted at the end of the request.

```php
$metadata = $client->metadata;

$metadata->set('variant', 'B');           // store a value
$metadata->set('promo', 'X', ttl: 3600);  // optional TTL in seconds

$metadata->has('variant');                // true
$metadata->get('variant');                // 'B' — throws if the key is missing, so guard with has()
$metadata->remove('variant');

foreach ($metadata as $key => $value) {   // iterate everything
    // ...
}
```

### Set metadata from a request

A common pattern is to capture data from the incoming request — here, the Google click id:

```php
use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class GoogleClickIdSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ClientContextInterface $clientContext)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'capture'];
    }

    public function capture(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->query->has('gclid')) {
            return;
        }

        $this->clientContext->getClient()->metadata->set(
            'google_click_id',
            $event->getRequest()->query->get('gclid'),
        );
    }
}
```

### Access the cookie directly

The cookie stores the client id plus the first/last seen timestamps. Read it through `CookieProviderInterface`:

```php
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;

final class SomeService
{
    public function __construct(private readonly CookieProviderInterface $cookieProvider)
    {
    }

    public function __invoke(): void
    {
        $cookie = $this->cookieProvider->getCookie();
        if (null === $cookie) {
            return; // visitor has no cookie yet
        }

        $cookie->clientId;    // the client id
        $cookie->firstSeenAt; // unix timestamp of first visit
        $cookie->lastSeenAt;  // unix timestamp of the previous visit
    }
}
```

The cookie is intentionally **not** `HttpOnly`, so you can also read it from JavaScript (e.g. to send the client id
to a third‑party tag).

### Skip storing the cookie (consent)

Because the cookie identifies a visitor, you may need consent before storing it. Listen to `PreStoreCookieEvent`
(dispatched on the response, before the cookie is written) and set `$event->store = false` to skip it for that
request:

```php
use Setono\ClientBundle\Event\PreStoreCookieEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CookieConsentSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PreStoreCookieEvent::class => 'onPreStore'];
    }

    public function onPreStore(PreStoreCookieEvent $event): void
    {
        if (!$this->hasConsent($event->request)) {
            $event->store = false;
        }
    }

    // ...
}
```

## Configuration

All options are optional; the defaults are shown below:

```yaml
setono_client:
    cookie:
        # Name of the cookie that holds the client id.
        # NOTE: changing this makes every visitor with the old cookie name look like a new client.
        name: setono_client_id

        # Cookie lifetime, expressed as any string strtotime() can parse.
        expiration: '+365 days'

    # Entity used to persist metadata. Override it with your own class to add mapped fields
    # or behaviour; it must implement Setono\ClientBundle\Entity\MetadataInterface.
    metadata_class: Setono\ClientBundle\Entity\Metadata
```

## How it works

- **Cookie** — on each main response, `StoreCookieSubscriber` writes/refreshes the cookie and bumps `lastSeenAt`.
  A new client id is generated only when no valid cookie is present.
- **Lazy metadata** — `$client->metadata` is a lazy ghost object. The database is queried only the first time you
  read a value, and a row is written only at the end of the request, and only if the metadata was actually changed.
- **Resolution chain** — `ClientContextInterface` is built from a chain of decorating services
  (`CachedClientContext` → `CookieBasedClientContext` → `DefaultClientContext`). The cached layer memoizes the client
  per request; the cookie‑based layer builds it from the cookie; the default layer creates a fresh, anonymous client.

## Extending the bundle

Every moving part is a service behind an interface, so you can swap or decorate it:

| Concern | Interface |
| --- | --- |
| Resolving the current client | `Setono\ClientBundle\Context\ClientContextInterface` |
| Reading the cookie | `Setono\ClientBundle\CookieProvider\CookieProviderInterface` |
| Loading metadata | `Setono\ClientBundle\MetadataProvider\MetadataProviderInterface` |
| Persisting metadata | `Setono\ClientBundle\MetadataPersister\MetadataPersisterInterface` |
| Metadata entity | `Setono\ClientBundle\Entity\MetadataInterface` |

To add behaviour, [decorate](https://symfony.com/doc/current/service_container/service_decoration.html) the relevant
service rather than replacing it outright — that is exactly how the bundle composes its own defaults.

## Contributing

```shell
git clone https://github.com/Setono/client-bundle.git
cd client-bundle
composer install
```

Quality tooling (also run in CI):

| Command | Purpose |
| --- | --- |
| `composer phpunit` | Run the test suite |
| `composer analyse` | Static analysis (PHPStan, `level: max`) |
| `composer check-style` / `composer fix-style` | Coding standard (ECS) |
| `composer infection` | Mutation testing (requires a coverage driver) |
| `vendor/bin/composer-dependency-analyser` | Detect unused/undeclared dependencies |

## License

This bundle is released under the [MIT License](LICENSE).

[ico-version]: https://poser.pugx.org/setono/client-bundle/v/stable
[ico-license]: https://poser.pugx.org/setono/client-bundle/license
[ico-github-actions]: https://github.com/Setono/client-bundle/actions/workflows/build.yaml/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/client-bundle/branch/master/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/client-bundle
[link-github-actions]: https://github.com/Setono/client-bundle/actions
[link-code-coverage]: https://codecov.io/gh/Setono/client-bundle
