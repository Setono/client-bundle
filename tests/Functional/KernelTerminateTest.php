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
    public function it_recognizes_the_client_on_kernel_terminate_when_the_request_did_not_use_it(): void
    {
        $this->browser->getCookieJar()->set(new BrowserCookie(self::COOKIE_NAME, (new Cookie('client-id'))->toString(), null, '/', 'localhost'));

        // The route doesn't use the client, and the veto keeps the cookie subscriber from using it
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
