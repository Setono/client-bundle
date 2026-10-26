<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional;

use Setono\Client\Metadata;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Shows how the metadata of a new client travels: it's stored in the pending metadata cookie, and persisted when the
 * client comes back with it. The test application's GoogleClickIdSubscriber sets the metadata
 */
final class NewClientMetadataTest extends FunctionalTestCase
{
    private const PENDING_METADATA_COOKIE_NAME = 'setono_client_id_pending_metadata';

    /**
     * @test
     */
    public function it_does_not_touch_the_database_for_a_new_client_that_never_comes_back(): void
    {
        $this->browser->request('GET', '/?gclid=abc');

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(
            [self::COOKIE_NAME, self::PENDING_METADATA_COOKIE_NAME],
            array_map(static fn (Cookie $cookie): string => $cookie->getName(), $this->browser->getResponse()->headers->getCookies()),
        );
        self::assertSame([], self::getExecutedQueries());
        self::assertSame(0, self::countStoredMetadata());
    }

    /**
     * @test
     */
    public function it_persists_the_metadata_when_the_new_client_comes_back(): void
    {
        $this->browser->request('GET', '/?gclid=abc');
        self::assertSame(0, self::countStoredMetadata());

        $this->browser->request('GET', '/');

        $metadata = self::fetchStoredMetadata($this->getClientId());
        self::assertIsArray($metadata);
        self::assertSame('abc', $metadata['gclid']);
        self::assertArrayHasKey(Metadata::EXPIRES_KEY, $metadata);

        $pendingMetadataCookies = array_values(array_filter(
            $this->browser->getResponse()->headers->getCookies(),
            static fn (Cookie $cookie): bool => self::PENDING_METADATA_COOKIE_NAME === $cookie->getName(),
        ));
        self::assertCount(1, $pendingMetadataCookies);
        self::assertTrue($pendingMetadataCookies[0]->isCleared());
        self::assertNull($this->browser->getCookieJar()->get(self::PENDING_METADATA_COOKIE_NAME));

        // from now on, the metadata is only loaded when it's used
        $this->browser->request('GET', '/');
        self::assertSame([], self::getExecutedQueries());
    }

    /**
     * @test
     */
    public function it_merges_the_metadata_into_metadata_persisted_before_the_new_client_came_back(): void
    {
        $this->browser->request('GET', '/?gclid=abc');

        // e.g. by a message handler that the first request dispatched
        self::insertMetadata($this->getClientId(), ['country' => 'DK']);

        $this->browser->request('GET', '/');

        $metadata = self::fetchStoredMetadata($this->getClientId());
        self::assertIsArray($metadata);
        self::assertSame('DK', $metadata['country']);
        self::assertSame('abc', $metadata['gclid']);
        self::assertIsArray($metadata[Metadata::EXPIRES_KEY]);
        self::assertSame(['gclid'], array_keys($metadata[Metadata::EXPIRES_KEY]));
    }

    /**
     * @test
     */
    public function it_persists_the_metadata_right_away_when_it_does_not_fit_in_the_pending_metadata_cookie(): void
    {
        $value = str_repeat('a', 2048);

        $this->browser->request('GET', '/metadata/set?key=foo&value=' . $value);

        self::assertResponseNotHasCookie(self::PENDING_METADATA_COOKIE_NAME);
        self::assertSame(['foo' => $value], self::fetchStoredMetadata($this->getClientId()));
    }

    /**
     * @test
     */
    public function it_persists_metadata_set_after_the_cookie_was_stored_right_away(): void
    {
        $this->browser->request('GET', '/?late_gclid=abc');

        self::assertResponseNotHasCookie(self::PENDING_METADATA_COOKIE_NAME);

        $metadata = self::fetchStoredMetadata($this->getClientId());
        self::assertIsArray($metadata);
        self::assertSame('abc', $metadata['gclid']);
    }

    /**
     * @test
     */
    public function it_does_not_persist_the_metadata_of_a_new_client_that_does_not_get_a_cookie(): void
    {
        $this->browser->request('GET', '/?gclid=abc&skip_cookie=1');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->browser->getResponse()->headers->getCookies());
        self::assertSame([], self::getExecutedQueries());
        self::assertSame(0, self::countStoredMetadata());
    }
}
