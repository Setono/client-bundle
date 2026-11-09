<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App\Context;

use Setono\Client\Client;
use Setono\ClientBundle\Client\ChangeAwareMetadata;
use Setono\ClientBundle\Context\ClientContextInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the interface. Like an application that recognizes a client by other means, e.g. by a logged-in user,
 * it makes the client that the "client_id" query parameter names the current client. It creates a client on every
 * call, like a decorator that doesn't keep the client
 */
#[AsDecorator(ClientContextInterface::class)]
final class QueryParameterClientContext implements ClientContextInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ClientContextInterface $decorated,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getClient(): Client
    {
        $clientId = $this->requestStack->getMainRequest()?->query->get('client_id');
        if (!is_string($clientId)) {
            return $this->decorated->getClient();
        }

        return new Client($clientId, new ChangeAwareMetadata());
    }
}
