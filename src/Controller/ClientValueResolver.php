<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Controller;

use Setono\Client\Client;
use Setono\ClientBundle\ClientFactory\ClientFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ClientValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly ClientFactoryInterface $clientFactory)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();
        if (!is_string($argumentType) || !is_a($argumentType, Client::class, true)) {
            return [];
        }

        return [$this->clientFactory->create()];
    }
}
