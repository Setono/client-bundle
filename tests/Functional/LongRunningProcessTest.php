<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

/**
 * Long-running runtimes, like FrankenPHP's worker mode, RoadRunner and Swoole, handle many requests
 * with the same container and only reset its services between requests
 */
final class LongRunningProcessTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->browser->disableReboot();
    }

    /**
     * @test
     */
    public function it_gives_each_new_visitor_its_own_client(): void
    {
        $this->browser->request('GET', '/');
        $firstClientId = $this->getClientId();

        $this->browser->getCookieJar()->clear();
        $this->browser->request('GET', '/');
        $secondClientId = $this->getClientId();

        self::assertNotSame($firstClientId, $secondClientId);
        self::assertSame($secondClientId, $this->browser->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_does_not_give_the_metadata_of_a_visitor_to_the_next_visitor(): void
    {
        $this->becomeReturningVisitor();
        $this->browser->request('GET', '/metadata/set?key=foo&value=bar');
        $firstClientId = $this->getClientId();

        $this->browser->getCookieJar()->clear();
        $this->becomeReturningVisitor();
        $this->browser->request('GET', '/metadata/set?key=baz&value=qux');
        $this->browser->request('GET', '/metadata');

        self::assertSame('{"baz":"qux"}', $this->browser->getResponse()->getContent());
        self::assertSame(['foo' => 'bar'], self::fetchStoredMetadata($firstClientId));
        self::assertSame(['baz' => 'qux'], self::fetchStoredMetadata($this->getClientId()));
    }
}
