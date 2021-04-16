Запускаем контейнеры командой `docker-compose up -d`

## 1. Устанавливаем PHPUnit bridge

1. Добавляем пакеты `symfony/phpunit-bridge`, `mockery/mockery` и `doctrine/doctrine-fixtures-bundle` в **dev-режиме**
1. Исправляем версию phpunit в файле `phpunit.xml.dist`
    ```xml
    <server name="SYMFONY_PHPUNIT_VERSION" value="9.4" />
    ```
1. Все дальнейшие команды выполняем в контейнере, куда входим командой `docker-compose exec -it php sh`
1. Выполняем команду `./vendor/bin/simple-phpunit --migrate-configuration`
1. Исправляем в composer.json секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/unit"
        }
    },
    ```

## 2. Пишем первый тест

1. Добавляем класс `UnitTests\Entity\TweetTest`
    ```php
    <?php
    
    namespace UnitTests\Entity;
    
    use App\Entity\Tweet;
    use App\Entity\User;
    use DateTime;
    use PHPUnit\Framework\TestCase;
    
    class TweetTest extends TestCase
    {
        public function tweetDataProvider(): array
        {
            $expectedPositive = [
                'id' => 5,
                'author' => 'Terry Pratchett',
                'text' => 'The Colour of Magic',
                'createdAt' => (new DateTime())->format('Y-m-d h:i:s'),
            ];
            $positiveTweet = $this->addAuthor($this->makeTweet($expectedPositive), $expectedPositive);
            $expectedNoAuthor = [
                'id' => 30,
                'author' => null,
                'text' => 'Unknown book',
                'createdAt' => (new DateTime())->format('Y-m-d h:i:s'),
            ];
            $expectedNoCreatedAt = [
                'id' => 42,
                'author' => 'Douglas Adams',
                'text' => 'The Hitchhiker\'s Guide to the Galaxy',
                'createdAt' => '',
            ];
            return [
                'positive' => [
                    $positiveTweet,
                    $expectedPositive,
                    0,
                ],
                'no author' => [
                    $this->makeTweet($expectedNoAuthor),
                    $expectedNoAuthor,
                    0
                ],
                'no createdAt' => [
                    $this->addAuthor($this->makeTweet($expectedNoCreatedAt), $expectedNoCreatedAt),
                    $expectedNoCreatedAt,
                    null
                ],
                'positive with delay' => [
                    $positiveTweet,
                    $expectedPositive,
                    2
                ],
            ];
        }
    
        /**
         * @dataProvider tweetDataProvider
         * @group time-sensitive
         */
        public function testToFeedReturnsCorrectValues(Tweet $tweet, array $expected, ?int $delay = null): void
        {
            $tweet = $this->setCreatedAtWithDelay($tweet, $delay);
            $actual = $tweet->toFeed();
    
            static::assertSame($expected, $actual, 'Tweet::toFeed should return correct result');
        }
    
        private function makeTweet(array $data): Tweet
        {
            $tweet = new Tweet();
            $tweet->setId($data['id']);
            $tweet->setText($data['text']);
    
            return $tweet;
        }
    
        private function addAuthor(Tweet $tweet, array $data): Tweet
        {
            $author = new User();
            $author->setLogin($data['author']);
            $tweet->setAuthor($author);
    
            return $tweet;
        }
    
        private function setCreatedAtWithDelay(Tweet $tweet, ?int $delay = null): Tweet
        {
            if ($delay !== null) {
                \sleep($delay);
                $tweet->setCreatedAt();
            }
    
            return $tweet;
        }
    }
    ```
1. Запускаем тесты командой `./vendor/bin/simple-phpunit`, видим 2 ошибки и 1 фейл

## 3. Исправляем ошибки

1. Исправляем ошибки в классе `App\Entity\Tweet` в методе `toFeed`
    ```php
    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'author' => isset($this->author) ? $this->author->getLogin() : null,
            'text' => $this->text,
            'createdAt' => isset($this->createdAt) ? $this->createdAt->format('Y-m-d h:i:s') : '',
        ];
    }
    ```
1. Запускаем тесты командой `./vendor/bin/simple-phpunit`, проверяем, что ошибки исправились

## 4. Исправляем фейл

1. В классе `UnitTests\Entity\TweetTest`
    1. исправляем вызовы `new DateTime()` в методе `tweetDataProvider`:
        ```php
        /**
         * @group time-sensitive
         */
        public function tweetDataProvider(): array
        {
            $expectedPositive = [
                'id' => 5,
                'author' => 'Terry Pratchett',
                'text' => 'The Colour of Magic',
                'createdAt' => DateTime::createFromFormat('U',(string)time())->format('Y-m-d h:i:s'),
            ];
            $positiveTweet = $this->addAuthor($this->makeTweet($expectedPositive), $expectedPositive);
            $expectedNoAuthor = [
                'id' => 30,
                'author' => null,
                'text' => 'Unknown book',
                'createdAt' => DateTime::createFromFormat('U',(string)time())->format('Y-m-d h:i:s'),
            ];
            $expectedNoCreatedAt = [
                'id' => 42,
                'author' => 'Douglas Adams',
                'text' => 'The Hitchhiker\'s Guide to the Galaxy',
                'createdAt' => '',
            ];
            return [
                'positive' => [
                    $positiveTweet,
                    $expectedPositive,
                    0,
                ],
                'no author' => [
                    $this->makeTweet($expectedNoAuthor),
                    $expectedNoAuthor,
                    0
                ],
                'no createdAt' => [
                    $this->addAuthor($this->makeTweet($expectedNoCreatedAt), $expectedNoCreatedAt),
                    $expectedNoCreatedAt,
                    null
                ],
                'positive with delay' => [
                    $positiveTweet,
                    $expectedPositive,
                    2
                ],
            ];
        }
        ```
    1. исправляем тест `testToFeedReturnsCorrectValues`
        ```php
        /**
         * @dataProvider tweetDataProvider
         * @group time-sensitive
         */
        public function testToFeedReturnsCorrectValues(Tweet $tweet, array $expected, ?int $delay = null): void
        {
            ClockMock::register(Tweet::class);
            $tweet = $this->setCreatedAtWithDelay($tweet, $delay);
            $actual = $tweet->toFeed();
    
            static::assertSame($expected, $actual, 'Tweet::toFeed should return correct result');
        }
        ```
   1. В классе `App\Entity\Tweet` исправляем метод `setCreatedAt`
       ```php
        /**
         * @Mapping\PrePersist
         */
        public function setCreatedAt(): void {
            $this->createdAt = DateTime::createFromFormat('U', (string)time());
        }
       ```
1. Запускаем тесты командой `./vendor/bin/simple-phpunit`, видим, что тесты проходят успешно

## 5. Добавляем тесты со стабами

1. Добавляем класс `UnitTests\Service\SubscriptionServiceTest`
    ```php
    <?php
    
    namespace UnitTests\Service;
    
    use App\Entity\User;
    use App\Service\SubscriptionService;
    use App\Service\UserService;
    use Doctrine\ORM\EntityManager;
    use Doctrine\ORM\EntityManagerInterface;
    use Doctrine\ORM\EntityRepository;
    use Mockery;
    use Mockery\MockInterface;
    use PHPUnit\Framework\TestCase;
    
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
           /** @var UserPasswordEncoderInterface $encoder */
           $encoder = Mockery::mock(UserPasswordEncoderInterface::class);
           /** @var PaginatedFinderInterface $finder */
           $finder = Mockery::mock(PaginatedFinderInterface::class); 
           $userService = new UserService(self::$entityManager, $encoder, $finder);
            $subscriptionService = new SubscriptionService(self::$entityManager, $userService);
    
            $actual = $subscriptionService->subscribe($authorId, $followerId);
    
            static::assertSame($expected, $actual, 'Subscribe should return correct result');
        }
    }
    ```
1. Запускаем тесты командой `./vendor/bin/simple-phpunit`, видим, что тесты проходят успешно

## 6. Переделываем на параллельный запуск

1. Копируем файл `phpunit.xml.dist` в `tests/unit/Entity` и в `tests/unit/Service`
1. Исправляем в обоих файлах описание `testsuite`
    1. В файле `tests/unit/Entity/phpunit.xml.dist`:
        ```xml
        <testsuite name="Entity Test Suite">
          <directory>.</directory>
        </testsuite>
        ```
    1. В файле `tests/unit/Service/phpunit.xml.dist`:
        ```xml
        <testsuite name="Service Test Suite">
          <directory>.</directory>
        </testsuite>
        ```
1. Запускаем тесты командой `./vendor/bin/simple-phpunit tests`, видим, что выполняется два раздельных запуска
1. Добавим задержку в метод `testSubscribeReturnsCorrectResult` класса `UnitTests\Service\SubscriptionServiceTest`
    ```php
    /**
    * @dataProvider subscribeDataProvider
    */
    public function testSubscribeReturnsCorrectResult(int $authorId, int $followerId, bool $expected): void
    {
        usleep(400000);
        /** @var UserPasswordEncoderInterface $encoder */
        $encoder = Mockery::mock(UserPasswordEncoderInterface::class);
        /** @var PaginatedFinderInterface $finder */
        $finder = Mockery::mock(PaginatedFinderInterface::class);
        $userService = new UserService(self::$entityManager, $encoder, $finder);
       $subscriptionService = new SubscriptionService(self::$entityManager, $userService);
    
       $actual = $subscriptionService->subscribe($authorId, $followerId);
    
       static::assertSame($expected, $actual, 'Subscribe should return correct result');
    }
    ```
1. Запускаем тесты ещё раз командой `./vendor/bin/simple-phpunit tests`, видим, что они точно выполняются параллельно
(завершаются параллельно оба набора)

## 7. Добавляем тест с моками

1. В классе `UnitTests\Service\SubscriptionServiceTest` добавим метод `testSubscribeReturnsAfterFirstError`
    ```php
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
        /** @var UserPasswordEncoderInterface $encoder */
        $encoder = Mockery::mock(UserPasswordEncoderInterface::class);
        /** @var PaginatedFinderInterface $finder */
        $finder = Mockery::mock(PaginatedFinderInterface::class);
        $userService = new UserService(self::$entityManager, $encoder, $finder);
        $subscriptionService = new SubscriptionService(self::$entityManager, $userService);
    
        $subscriptionService->subscribe(self::INCORRECT_AUTHOR, self::INCORRECT_FOLLOWER);
    }
    ```
1. Запускаем тесты ещё раз командой `./vendor/bin/simple-phpunit tests`, видим risky test
1. В классе `UnitTests\SubscriptionServiceTest` добавляем трейт `MockeryPHPUnitIntegration`
    ```php
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    ```
1. Запускаем тесты ещё раз командой `./vendor/bin/simple-phpunit tests`, видим ошибку
1. В классе `UnitTests\SubscriptionServiceTest` исправляем метод `testSubscribeReturnsAfterFirstError` 
    ```php
    public function testSubscribeReturnsAfterFirstError(): void
    {
        /** @var MockInterface|EntityRepository $repository */
        $repository = Mockery::mock(EntityRepository::class);
        $repository->shouldReceive('find')->with(self::INCORRECT_AUTHOR)->andReturn(null)->once();
        $repository->shouldReceive('find')->with(self::INCORRECT_FOLLOWER)->never();
        /** @var MockInterface|EntityManagerInterface $repository */
        self::$entityManager = Mockery::mock(EntityManagerInterface::class);
        self::$entityManager->shouldReceive('getRepository')->with(User::class)->andReturn($repository);
        self::$entityManager->shouldReceive('persist');
        self::$entityManager->shouldReceive('flush');
        $userService = new UserService(self::$entityManager);
        $subscriptionService = new SubscriptionService(self::$entityManager, $userService);
    
        $subscriptionService->subscribe(self::INCORRECT_AUTHOR, self::INCORRECT_FOLLOWER);
    }
    ```
1. Запускаем тесты ещё раз командой `./vendor/bin/simple-phpunit tests`, видим, что тесты проходят

## 8. Добавляем тест с фикстурами

1. Добавляем класс `UnitTests\FixturedTestCase`
    ```php
    <?php
    
    namespace UnitTests;
    
    use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
    use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
    use Doctrine\Common\DataFixtures\FixtureInterface;
    use Doctrine\Common\DataFixtures\Purger\ORMPurger;
    use Doctrine\ORM\EntityManager;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
    use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
    use Symfony\Component\DependencyInjection\ContainerInterface;
    
    abstract class FixturedTestCase extends WebTestCase
    {
        private ?ContainerAwareLoader $fixtureLoader;

        private AbstractExecutor $fixtureExecutor;
    
        public function setUp(): void
        {
            self::bootKernel();
            $this->initFixtureExecutor();
        }
    
        public function tearDown(): void
        {
            $em = self::$kernel->getContainer()->get('doctrine')->getManager();
            $em->clear();
            $em->getConnection()->close();
            gc_collect_cycles();
            parent::tearDown();
        }
    
        protected function getContainer(): ContainerInterface
        {
            return self::$kernel->getContainer();
        }
    
        protected function initFixtureExecutor(): void
        {
            /** @var EntityManager $entityManager */
            $entityManager = $this->getContainer()->get('doctrine')->getManager();
            $this->fixtureExecutor = new ORMExecutor($entityManager, new ORMPurger($entityManager));
        }
    
        protected function addFixture(FixtureInterface $fixture): void
        {
            $this->getFixtureLoader()->addFixture($fixture);
        }
    
        protected function executeFixtures(): void
        {
            $this->fixtureExecutor->execute($this->getFixtureLoader()->getFixtures());
            $this->fixtureLoader = null;
        }
    
        protected function getReference(string $refName)
        {
            return $this->fixtureExecutor->getReferenceRepository()->getReference($refName);
        }
    
        private function getFixtureLoader(): ContainerAwareLoader
        {
            if (!isset($this->fixtureLoader)) {
                $this->fixtureLoader = new ContainerAwareLoader($this->getContainer());
            }
    
            return $this->fixtureLoader;
        }
    
        public function getDoctrineManager(): EntityManagerInterface
        {
            return $this->getContainer()->get('doctrine')->getManager();
        }
    }
    ```
1. Добавляем класс `UnitTests\Fixtures\MultipleUsersFixture`
    ```php
    <?php
    
    namespace UnitTests\Fixtures;
    
    use App\Entity\User;
    use Doctrine\Bundle\FixturesBundle\Fixture;
    use Doctrine\Persistence\ObjectManager;
    
    class MultipleUsersFixture extends Fixture
    {
        public const PRATCHETT = 'Terry Pratchett';
        public const TOLKIEN = 'John R.R. Tolkien';
        public const CARROLL = 'Lewis Carrol';
        public const ALL_FOLLOWER = 'Follows all';
        public const CARROLL_PRATCHETT_FOLLOWER = 'Follows Carrol and Pratchett';
        public const CARROLL_TOLKIEN_FOLLOWER = 'Follows Carrol and Tolkien';
    
        public function load(ObjectManager $manager): void
        {
            $this->addReference(self::PRATCHETT, $this->makeUser($manager, self::PRATCHETT));
            $this->addReference(self::TOLKIEN, $this->makeUser($manager, self::TOLKIEN));
            $this->addReference(self::CARROLL, $this->makeUser($manager, self::CARROLL));
            $this->addReference(self::ALL_FOLLOWER, $this->makeUser($manager, self::ALL_FOLLOWER));
            $this->addReference(
                self::CARROLL_PRATCHETT_FOLLOWER,
                $this->makeUser($manager, self::CARROLL_PRATCHETT_FOLLOWER)
            );
            $this->addReference(
                self::CARROLL_TOLKIEN_FOLLOWER,
                $this->makeUser($manager, self::CARROLL_TOLKIEN_FOLLOWER)
            );
            $manager->flush();
        }
    
        private function makeUser(ObjectManager $manager, string $login): User
        {
            $user = new User();
            $user->setLogin($login);
            $user->setPassword("{$login}_password");
            $user->setRoles([]);
            $user->setPhone('+1111111111');
            $user->setEmail('user@nomail.com');
            $user->setPreferred('email');
            $user->setAge(100);
            $manager->persist($user);
    
            return $user;
        }
    }
    ```
1. Добавляем класс `UnitTests\Fixtures\MultipleTweetsFixture`
    ```php
    <?php
    
    namespace UnitTests\Fixtures;
    
    use App\Entity\Tweet;
    use App\Entity\User;
    use Doctrine\Bundle\FixturesBundle\Fixture;
    use Doctrine\Persistence\ObjectManager;
    
    class MultipleTweetsFixture extends Fixture
    {
        public function load(ObjectManager $manager): void
        {
            /** @var User $pratchett */
            $pratchett = $this->getReference(MultipleUsersFixture::PRATCHETT);
            /** @var User $tolkien */
            $tolkien = $this->getReference(MultipleUsersFixture::TOLKIEN);
            /** @var User $carroll */
            $carroll = $this->getReference(MultipleUsersFixture::CARROLL);
            $this->makeTweet($manager, $tolkien, 'Hobbit');
            $this->makeTweet($manager, $pratchett, 'Colours of Magic');
            $this->makeTweet($manager, $tolkien, 'Lords of the Rings');
            $this->makeTweet($manager, $pratchett, 'Soul Music');
            $this->makeTweet($manager, $carroll, 'Alice in Wonderland');
            $this->makeTweet($manager, $pratchett, 'Through the Looking-Glass');
            $manager->flush();
        }
    
        private function makeTweet(ObjectManager $manager, User $author, string $text): void
        {
            $tweet = new Tweet();
            $tweet->setAuthor($author);
            $tweet->setText($text);
            $manager->persist($tweet);
            sleep(1);
        }
    }
    ```
1. Добавляем класс `MultipleSubscriptionsFixture`
    ```php
    <?php
    
    namespace UnitTests\Fixtures;
    
    use App\Entity\Subscription;
    use App\Entity\User;
    use Doctrine\Bundle\FixturesBundle\Fixture;
    use Doctrine\Persistence\ObjectManager;
    
    class MultipleSubscriptionsFixture extends Fixture
    {
        public function load(ObjectManager $manager)
        {
            /** @var User $pratchett */
            $pratchett = $this->getReference(MultipleUsersFixture::PRATCHETT);
            /** @var User $tolkien */
            $tolkien = $this->getReference(MultipleUsersFixture::TOLKIEN);
            /** @var User $carroll */
            $carroll = $this->getReference(MultipleUsersFixture::CARROLL);
            /** @var User $allFollower */
            $allFollower = $this->getReference(MultipleUsersFixture::ALL_FOLLOWER);
            /** @var User $carrollPratchettFollower */
            $carrollPratchettFollower = $this->getReference(MultipleUsersFixture::CARROLL_PRATCHETT_FOLLOWER);
            /** @var User $carrollTolkienFollower */
            $carrollTolkienFollower = $this->getReference(MultipleUsersFixture::CARROLL_TOLKIEN_FOLLOWER);
            $this->makeSubscription($manager, $pratchett, $allFollower);
            $this->makeSubscription($manager, $pratchett, $carrollPratchettFollower);
            $this->makeSubscription($manager, $tolkien, $allFollower);
            $this->makeSubscription($manager, $tolkien, $carrollTolkienFollower);
            $this->makeSubscription($manager, $carroll, $allFollower);
            $this->makeSubscription($manager, $carroll, $carrollPratchettFollower);
            $this->makeSubscription($manager, $carroll, $carrollTolkienFollower);
            $manager->flush();
        }
    
        private function makeSubscription(ObjectManager $manager, User $author, User $follower): void
        {
            $subscription = new Subscription();
            $subscription->setAuthor($author);
            $subscription->setFollower($follower);
            $manager->persist($subscription);
        }
    }
    ```
1. В классе `App\Repository\TweetRepository` добавляем метод `getByAuthorIds`
    ```php
    /**
     * @param int[] $authorIds
     *
     * @return Tweet[]
     */
    public function getByAuthorIds(array $authorIds, int $count): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('t')
            ->from($this->getClassName(), 't')
            ->where($qb->expr()->in('IDENTITY(t.author)', ':authorIds'))
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($count);

        $qb->setParameter('authorIds', $authorIds);

        return $qb->getQuery()->getResult();
    }
    ```
1. В классе `App\ServiceTweetService` добавляем метод `getFeed`
    ```php
    /**
     * @param int[] $authorIds
     *
     * @return Tweet[]
     */
    public function getFeed(array $authorIds, int $count): array {
        /** @var TweetRepository $tweetRepository */
        $tweetRepository = $this->entityManager->getRepository(Tweet::class);

        return $tweetRepository->getByAuthorIds($authorIds, $count);
    }
    ```
1. В классе `App\Service\SubscriptionService` добавляем методы `getAuthorIds` и `getSubscriptionsByFollowerId`
    ```php
    /**
     * @return int[]
     */
    public function getAuthorIds(int $followerId): array
    {
        $subscriptions = $this->getSubscriptionsByFollowerId($followerId);
        $mapper = static function(Subscription $subscription) {
            return $subscription->getAuthor()->getId();
        };

        return array_map($mapper, $subscriptions);
    }

    /**
     * @return Subscription[]
     */
    private function getSubscriptionsByFollowerId(int $followerId): array
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $follower = $userRepository->find($followerId);
        if (!($follower instanceof User)) {
            return [];
        }
        $subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
        return $subscriptionRepository->findBy(['follower' => $follower]) ?? [];
    }
    ```
1. В классе `App\Service\FeedService`
    1. Добавляем зависимость от `TweetService`
    1. Добавляем метод `getFeedFromTweets`
        ```php
        public function getFeedFromTweets(int $userId, int $count): array
        {
            return $this->tweetService->getFeed($this->subscriptionService->getAuthorIds($userId), $count);
        }
        ```
1. Добавляем класс `UnitTests\Service\FeedServiceTest`
    ```php
    <?php
    
    namespace UnitTests\Service;
    
    use App\Entity\Tweet;
    use App\Service\AsyncService;
    use App\Service\FeedService;
    use App\Service\SubscriptionService;
    use App\Service\TweetService;
    use App\Service\UserService;
    use Mockery;
    use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
    use Symfony\Contracts\Cache\TagAwareCacheInterface;
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
            /** @var UserPasswordEncoderInterface $encoder */
            $encoder = $this->getContainer()->get('security.password_encoder');
            /** @var TagAwareCacheInterface $cache */
            $cache = $this->getContainer()->get('redis_adapter');
            /** @var PaginatedFinderInterface $finder */
            $finder = Mockery::mock(PaginatedFinderInterface::class);
            $userService = new UserService($this->getDoctrineManager(), $encoder, $finder);
            $tweetService = new TweetService($this->getDoctrineManager(), $cache);
            $subscriptionService = new SubscriptionService($this->getDoctrineManager(), $userService);
            $feedService = new FeedService(
                $this->getDoctrineManager(),
                $subscriptionService,
                Mockery::mock(AsyncService::class),
                $tweetService
            );
            $follower= $userService->findUserByLogin($followerLogin);
    
            $feed = $feedService->getFeedFromTweets($follower->getId(), $count);
    
            self::assertSame($expected, array_map(static fn(Tweet $tweet) => $tweet->getText(), $feed));
        }
    }
    ```
1. Добавляем файл `config\services_test.yaml`
    ```yaml
    services:
      redis_adapter:
        class: Symfony\Component\Cache\Adapter\RedisTagAwareAdapter
        arguments:
          - '@redis_client'
          - 'my_app'
        public: true
    ```
1. Добавим в `docker-compose.yml` ещё один контейнер с тестовой БД (не забудьте указать volume `postgresql_test`)
    ```yaml
    postgres_test:
        image: postgres:11
        ports:
          - 25432:5432
        container_name: 'postgresql_test'
        working_dir: /app
        restart: always
        environment:
          POSTGRES_DB: 'twitter'
          POSTGRES_USER: 'user'
          POSTGRES_PASSWORD: 'password'
        volumes:
          - dump:/app/dump
          - postgresql_test:/var/lib/postgresql/data
    ```
1. Добавим в файл `.env.test` DSN для тестовой БД
    ```shell script
    DATABASE_URL=postgresql://user:password@postgresql_test:5432/twitter?serverVersion=11&charset=utf8
    ```
1. Перезапускаем контейнеры
    ```shell
    docker-compose stop
    docker-compose up -d
    ```
1. Накатываем миграции на тестовую базу командой `php bin/console doctrine:migrations:migrate --env=test`
1. Запускаем тесты командой `./vendor/bin/simple-phpunit tests/unit/Service/FeedServiceTest.php`, видим ошибку
   автозагрузки
1. Перегенерируем файлы автозагрузки командой `composer dump-autoload`
1. Ещё раз запускаем тесты командой `./vendor/bin/simple-phpunit tests/unit/Service/FeedServiceTest.php`, видим, что они 
   проходят
