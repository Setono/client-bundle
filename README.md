# Integrate the client library into your Symfony application

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

Integrates the client library into your Symfony application.

Out of the box, the bundle will store a cookie named `setono_client_id` which contains the client id, a created timestamp and a last seen timestamp.

It will also create a new table with metadata for each client id. The metadata functionality is lazy loaded, so if you don't use it you won't have to worry about it.

## Installation

To install this bundle, simply run:

```shell
composer require setono/client-bundle
```

### Migrate your database

```shell
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Usage

### Access the `Client` object along with some metadata

```php
use Setono\Client\Client;

final class YourController extends AbstractController
{
    public function index(Client $client): Response
    {
        return $this->render('your_template.html.twig', [
            'id' => $client->id,
            'some_metadata' => $client->metadata->get('some_metadata_key'), // this call will initialize the metadata object from the database
        ]);
    }
}
```

### Access the cookie

```php
use Setono\ClientBundle\CookieProvider\CookieProviderInterface;

final class YourService
{
    public function __construct(private readonly CookieProviderInterface $cookieProvider)
    {}
    
    public function call(): void
    {
        $cookie = $this->cookieProvider->getCookie();
        if(null === $cookie) {
            return; // no cookie found
        }
        
        $clientId = $cookie->clientId; // the client id
        $created = $cookie->firstSeenAt; // the timestamp when the client was first seen
        $lastSeen = $cookie->lastSeenAt; // the timestamp when the client was last seen
    }
}
```

[ico-version]: https://poser.pugx.org/setono/client-bundle/v/stable
[ico-license]: https://poser.pugx.org/setono/client-bundle/license
[ico-github-actions]: https://github.com/Setono/client-bundle/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/client-bundle/branch/badge.svg

[link-packagist]: https://packagist.org/packages/setono/client-bundle
[link-github-actions]: https://github.com/Setono/client-bundle/actions
[link-code-coverage]: https://codecov.io/gh/Setono/client-bundle
