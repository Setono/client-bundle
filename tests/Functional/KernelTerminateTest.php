<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\Client\Cookie;
use Setono\ClientBundle\Tests\Functional\App\EventSubscriber\RecordClientOnTerminateSubscriber;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;

/**
 * Symfony removes the request from the request stack before it dispatches kernel.terminate, which runs after the
 * response is sent. RecordClientOnTerminateSubscriber reads the current client there
 */
final class KernelTerminateTest extends FunctionalTestCase
{
    /**
     * @test
     */
    public function it_gives_the_listeners_of_kernel_terminate_the_current_client(): void
    {
        $this->browser->request('GET', '/?record_client_on_terminate=1');

        self::assertSame($this->getClientId(), self::getRecordedClientId());
    }

    /**
     * @test
     */
    public function it_gives_the_listeners_of_kernel_terminate_the_current_client_when_the_application_did_not_use_it(): void
    {
        $this->browser->getCookieJar()->set(new BrowserCookie(self::COOKIE_NAME, (new Cookie('client-id'))->toString(), null, '/', 'localhost'));

        // Neither the route nor the vetoed cookie subscriber uses the client, but StoreMetadataSubscriber gets it on
        // kernel.finish_request of every main request, while the request is still on the request stack
        $this->browser->request('GET', '/no-client?skip_cookie=1&record_client_on_terminate=1');

        self::assertSame('client-id', self::getRecordedClientId());
    }

    private static function getRecordedClientId(): ?string
    {
        $subscriber = self::getContainer()->get(RecordClientOnTerminateSubscriber::class);
        self::assertInstanceOf(RecordClientOnTerminateSubscriber::class, $subscriber);

        return $subscriber->clientId;
    }
}
