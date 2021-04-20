<?php

namespace UnitTests\Service;

use App\Entity\Tweet;
use App\Service\AsyncService;
use App\Service\FeedService;
use App\Service\SubscriptionService;
use App\Service\TweetService;
use App\Service\UserService;
use Mockery;
use UnitTests\FixturedTestCase;
use UnitTests\Fixtures\MultipleSubscriptionsFixture;
use UnitTests\Fixtures\MultipleTweetsFixture;
use UnitTests\Fixtures\MultipleUsersFixture;

class FeedServiceTest extends FixturedTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->addFixture(new MultipleUsersFixture());
        $this->addFixture(new MultipleTweetsFixture());
        $this->addFixture(new MultipleSubscriptionsFixture());
        $this->executeFixtures();
    }

    public function getFeedFromTweetsDataProvider(): array
    {
        return [
            'all authors, all tweets' => [
                MultipleUsersFixture::ALL_FOLLOWER,
                6,
                [
                    'Through the Looking-Glass',
                    'Alice in Wonderland',
                    'Soul Music',
                    'Lords of the Rings',
                    'Colours of Magic',
                    'Hobbit',
                ]
            ]
        ];
    }

    /**
     * @dataProvider getFeedFromTweetsDataProvider
     */
    public function testGetFeedFromTweetsReturnsCorrectResult(string $followerLogin, int $count, array $expected): void
    {
        $userService = new UserService($this->getDoctrineManager());
        $tweetService = new TweetService($this->getDoctrineManager());
        $subscriptionService = new SubscriptionService($this->getDoctrineManager(), $userService);
        $feedService = new FeedService(
            $this->getDoctrineManager(),
            $subscriptionService,
            Mockery::mock(AsyncService::class),
            $tweetService
        );
        $follower= $userService->findByLogin($followerLogin);

        $feed = $feedService->getFeedFromTweets($follower->getId(), $count);

        self::assertSame($expected, array_map(static fn(Tweet $tweet) => $tweet->getText(), $feed));
    }
}
