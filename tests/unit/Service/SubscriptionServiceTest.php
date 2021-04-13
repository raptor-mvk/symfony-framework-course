<?php

namespace UnitTests\Service;

use App\Entity\User;
use App\Service\SubscriptionService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SubscriptionServiceTest extends TestCase
{
    /** @var EntityManagerInterface|MockInterface */
    private static $entityManager;
    private const CORRECT_AUTHOR = 1;
    private const CORRECT_FOLLOWER = 2;
    private const INCORRECT_AUTHOR = 3;
    private const INCORRECT_FOLLOWER = 4;

    public static function setUpBeforeClass(): void
    {
        /** @var MockInterface|EntityRepository $repository */
        $repository = Mockery::mock(EntityRepository::class);
        $repository->shouldReceive('find')->with(self::CORRECT_AUTHOR)->andReturn(new User());
        $repository->shouldReceive('find')->with(self::INCORRECT_AUTHOR)->andReturn(null);
        $repository->shouldReceive('find')->with(self::CORRECT_FOLLOWER)->andReturn(new User());
        $repository->shouldReceive('find')->with(self::INCORRECT_FOLLOWER)->andReturn(null);
        /** @var MockInterface|EntityManagerInterface $repository */
        self::$entityManager = Mockery::mock(EntityManagerInterface::class);
        self::$entityManager->shouldReceive('getRepository')->with(User::class)->andReturn($repository);
        self::$entityManager->shouldReceive('persist');
        self::$entityManager->shouldReceive('flush');
    }

    public function subscribeDataProvider(): array
    {
        return [
            'both correct' => [self::CORRECT_AUTHOR, self::CORRECT_FOLLOWER, true],
            'author incorrect' => [self::INCORRECT_AUTHOR, self::CORRECT_FOLLOWER, false],
            'follower incorrect' => [self::CORRECT_AUTHOR, self::INCORRECT_FOLLOWER, false],
            'both incorrect' => [self::INCORRECT_AUTHOR, self::INCORRECT_FOLLOWER, false],
        ];
    }

    /**
     * @dataProvider subscribeDataProvider
     */
    public function testSubscribeReturnsCorrectResult(int $authorId, int $followerId, bool $expected): void
    {
        usleep(400000);
        $userService = new UserService(
            self::$entityManager,
            Mockery::mock(UserPasswordEncoderInterface::class),
            Mockery::mock(PaginatedFinderInterface::class)
        );
        $subscriptionService = new SubscriptionService(self::$entityManager, $userService);

        $actual = $subscriptionService->subscribe($authorId, $followerId);

        static::assertSame($expected, $actual, 'Subscribe should return correct result');
    }

    public function testSubscribeReturnsAfterFirstError(): void
    {
        /** @var MockInterface|EntityRepository $repository */
        $repository = Mockery::mock(EntityRepository::class);
        $repository->shouldReceive('find')->with(self::INCORRECT_AUTHOR)->andReturn(null)->never();
        $repository->shouldReceive('find')->with(self::INCORRECT_FOLLOWER)->never();
        /** @var MockInterface|EntityManagerInterface $repository */
        self::$entityManager = Mockery::mock(EntityManagerInterface::class);
        self::$entityManager->shouldReceive('getRepository')->with(User::class)->andReturn($repository);
        self::$entityManager->shouldReceive('persist');
        self::$entityManager->shouldReceive('flush');
        $userService = new UserService(
            self::$entityManager,
            Mockery::mock(UserPasswordEncoderInterface::class),
            Mockery::mock(PaginatedFinderInterface::class)
        );
        $subscriptionService = new SubscriptionService(self::$entityManager, $userService);

        $subscriptionService->subscribe(self::INCORRECT_AUTHOR, self::INCORRECT_FOLLOWER);
    }
}
