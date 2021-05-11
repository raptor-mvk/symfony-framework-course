# 1. Memcached в качестве кэша Doctrine

## 1. Устанавливаем Memcached

1. Добавляем в файл `docker/Dockerfile`
    1. Установку пакета `libmemcached-dev` через `apk`
    1. Установку расширения `memcached` через `pecl`
    1. Включение расширения командой `echo "extension=memcached.so" > /usr/local/etc/php/conf.d/memcached.ini`
1. Добавляем сервис Memcached в `docker-compose.yml`
    ```yaml
    memcached:
        image: memcached:latest
        container_name: 'memcached'
        restart: always
        ports:
           - 11211:11211
    ```
1. В файл `.env` добавляем
    ```shell
    MEMCACHED_DSN=memcached://memcached:11211
    ```
1. Пересобираем и запускаем контейнеры командой `docker-compose up -d --build`
1. Подключаемся к Memcached командой `telnet 127.0.0.1 11211` и проверяем, что он пустой (команда `stats items`)

## 2. Добавляем данные и метод для их извлечения

1. Добавим в БД 10 тысяч случайных твитов запросом
    ```sql
    INSERT INTO tweet (created_at, updated_at, author_id, text)
    SELECT NOW(), NOW(), 1, md5(random()::TEXT) FROM generate_series(1,10000);
    ```
1. Добавляем класс `App\Repository\TweetRepository`
    ```php
    <?php
    
    namespace App\Repository;
    
    use App\Entity\Tweet;
    use Doctrine\ORM\EntityRepository;
    
    class TweetRepository extends EntityRepository
    {
        /**
         * @return Tweet[]
         */
        public function getTweets(int $page, int $perPage): array
        {
            $qb = $this->getEntityManager()->createQueryBuilder();
            $qb->select('t')
                ->from($this->getClassName(), 't')
                ->orderBy('t.id', 'DESC')
                ->setFirstResult($perPage * $page)
                ->setMaxResults($perPage);
    
            return $qb->getQuery()->getResult();
        }
    }
    ```
1. Добавляем класс `App\Service\TweetService`
    ```php
    <?php
    
    namespace App\Service;
    
    use App\Entity\Tweet;
    use App\Repository\TweetRepository;
    use Doctrine\ORM\EntityManagerInterface;
    
    class TweetService
    {
        private EntityManagerInterface $entityManager;
    
        public function __construct(EntityManagerInterface $entityManager)
        {
            $this->entityManager = $entityManager;
        }
    
        /**
         * @return Tweet[]
         */
        public function getTweets(int $page, int $perPage): array
        {
            /** @var TweetRepository $TweetRepository */
            $tweetRepository = $this->entityManager->getRepository(Tweet::class);
    
            return $tweetRepository->getTweets($page, $perPage);
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\GetTweets\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\GetTweets\v1;
    
    use App\Entity\Tweet;
    use App\Service\TweetService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait;
    
        private TweetService $tweetService;
    
        public function __construct(TweetService $tweetService, ViewHandlerInterface $viewHandler)
        {
            $this->tweetService = $tweetService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Get("/api/v1/tweet")
         */
        public function getTweetsAction(Request $request): Response
        {
            $perPage = $request->query->get('perPage');
            $page = $request->query->get('page');
            $tweets = $this->tweetService->getTweets($page ?? 0, $perPage ?? 20);
            $code = empty($tweets) ? 204 : 200;
            $view = $this->view(['tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $tweets)], $code);
    
            return $this->handleView($view);
        }
    }
    ```
1. В классе `App\Entity\Tweet`
    1. Исправляем аннотацию перед классом
        ```php
        /**
         * @ORM\Table(
         *     name="tweet",
         *     indexes={
         *         @ORM\Index(name="tweet__author_id__ind", columns={"author_id"})
         *     }
         * )
         * @ORM\Entity(repositoryClass="App\Repository\TweetRepository")
         */
        ```
    1. Исправляем метод `toArray`
        ```php
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->author->getLogin(),
                'text' => $this->text,
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            ];
        }
        ```
1. Выполняем запрос Get Tweet list из Postman-коллекции v5, видим, что результат возвращается

## 3. Включаем кэширование в Doctrine

1. Исправляем файл `config/packages/doctrine.yaml`:
    1. Добавляем в секцию `orm`
        ```yaml
        metadata_cache_driver:
            type: service
            id: doctrine.cache.memcached
        query_cache_driver:
            type: service
            id: doctrine.cache.memcached
        result_cache_driver:
            type: service
            id: doctrine.cache.memcached
        ```
    1. Добавляем секцию `services`
        ```yaml
        services:
            memcached.doctrine:
                class: Memcached
                factory: Symfony\Component\Cache\Adapter\MemcachedAdapter::createConnection
                arguments:
                    - '%env(MEMCACHED_DSN)%'
                    - PREFIX_KEY: 'my_app_doctrine'
        
            doctrine.cache.memcached:
                class: Doctrine\Common\Cache\MemcachedCache
                calls:
                    - ['setMemcached', ['@memcached.doctrine']]
        ```    
1. Выполняем запрос Get Tweet list из Postman-коллекции v5 для прогрева кэша
1. Проверяем, что кэш прогрелся
    1. В Memcached выполняем `stats items`, видим там запись (или две записи)
    1. Выводим каждую запись командой `stats cachedump K 1000`, где K - идентификатор записи
    1. Получаем содержимое ключей командой `get KEY`, где `KEY` - ключ из записи
    1. Удостоверяемся, что это query и metadata кэши

## 4. Добавляем кэширование результата запроса

1. Включаем result cache в класс `App\Repository\TweetRepository` в методе `getTweets` в последней строке
    ```php
    return $qb->getQuery()->enableResultCache(null, "tweets_{$page}_{$perPage}")->getResult();
    ```
1. Выполняем запрос Get Tweet list из Postman-коллекции v5 для прогрева кэша
1. В Memcached выполняем `get my_app_doctrine[tweets_PAGE_PER_PAGE][1]`, где `PAGE` и `PER_PAGE` - значения
одноимённых параметров запроса, видим содержимое result cache

# 2. Redis в качестве кэша на уровне приложения

## 1. Подключаем redis

1. Для включения кэша на уровне приложения в файле `config/packages/cache.yaml` добавляем в секцию `cache`
    ```yaml
    app: cache.adapter.redis
    default_redis_provider: '%env(REDIS_DSN)%'
    ```
1. В файл `.env` добавляем
    ```shell
    REDIS_DSN=redis://redis:6379
    ```
1. В `docker-compose.yml` добавляем проброс порта в сервис `redis`
    ```yaml
    redis:
        container_name: 'redis'
        image: redis
        ports:
          - 6379:6379
    ```
1. Перезапускаем контейнеры
    ```shell
    docker-compose stop
    docker-compose up -d
    ```
1. Подключаемся к Redis командой `telnet 127.0.0.1 6379`
1. Выполняем `keys *`, видим записи от Sentry

## 2. Подключаем кэш на уровне приложения

1. Добавляем кэш в класс `App\Service\TweetService`
    1. Добавляем параметр типа `CacheItemPoolInterface` в конструктор
    1. Исправляем метод `getTweets`
        ```php
        /**
         * @return Tweet[]
         *
         * @throws \Psr\Cache\InvalidArgumentException
         */
        public function getTweets(int $page, int $perPage): array
        {
            /** @var TweetRepository $tweetRepository */
            $tweetRepository = $this->entityManager->getRepository(Tweet::class);
    
            $tweetsItem = $this->cacheItemPool->getItem("tweets_{$page}_{$perPage}");
            if (!$tweetsItem->isHit()) {
                $tweets = $tweetRepository->getTweets($page, $perPage);
                $tweetsItem->set(array_map(static fn(Tweet $tweet) => $tweet->toArray(), $tweets));
                $this->cacheItemPool->save($tweetsItem);
            }
    
            return $tweetsItem->get();
        }
        ```
1. В классе `App\Controller\Api\GetTweets\v1\Controller` исправляем метод `getTweetsAction`
    ```php
    /**
     * @Rest\Get("/api/v1/tweet")
     */
    public function getTweetsAction(Request $request): Response
    {
        $perPage = $request->query->get('perPage');
        $page = $request->query->get('page');
        $tweets = $this->tweetService->getTweets($page ?? 0, $perPage ?? 20);
        $code = empty($tweets) ? 204 : 200;
        $view = $this->view(['tweets' => $tweets], $code);

        return $this->handleView($view);
    }
    ```   
1. Выполняем запрос Get Tweet list из Postman-коллекции v5 для прогрева кэша
1. В Redis ищем ключи от приложения командой `keys *tweets*`
1. Выводим найденный ключ командой `get KEY`, где `KEY` - найденный ключ

## 3. Подсчитываем количество cache hit/miss

1. Добавляем декоратор для подсчёта cache hit/miss (класс `App\Symfony\CountingAdapterDecorator`)
    ```php
    <?php
    
    namespace App\Symfony;
    
    use StatsdBundle\Client\StatsdAPIClient;
    use Psr\Cache\CacheItemInterface;
    use Psr\Cache\InvalidArgumentException;
    use Psr\Log\LoggerAwareInterface;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\Cache\Adapter\AbstractAdapter;
    use Symfony\Component\Cache\Adapter\AdapterInterface;
    use Symfony\Component\Cache\CacheItem;
    use Symfony\Component\Cache\ResettableInterface;
    use Symfony\Contracts\Cache\CacheInterface;
    
    class CountingAdapterDecorator implements AdapterInterface, CacheInterface, LoggerAwareInterface, ResettableInterface
    {
        private const STATSD_HIT_PREFIX = 'cache.hit.';
        private const STATSD_MISS_PREFIX = 'cache.miss.';
    
        private AbstractAdapter $adapter;
        private StatsdAPIClient $statsdAPIClient;
    
        public function __construct(AbstractAdapter $adapter, StatsdAPIClient $statsdAPIClient)
        {
            $this->adapter = $adapter;
            $this->statsdAPIClient = $statsdAPIClient;
            $this->adapter->setCallbackWrapper(null);
        }
    
        public function getItem($key): CacheItem
        {
            $result = $this->adapter->getItem($key);
            $this->incCounter($result);
    
            return $result;
        }
    
        /**
         * @param string[] $keys
         *
         * @return iterable
         *
         * @throws InvalidArgumentException
         */
        public function getItems(array $keys = []): array
        {
            $result = $this->adapter->getItems($keys);
            foreach ($result as $item) {
                $this->incCounter($item);
            }
    
            return $result;
        }
    
        public function clear(string $prefix = ''): bool
        {
            return $this->adapter->clear($prefix);
        }
    
        public function get(string $key, callable $callback, float $beta = null, array &$metadata = null)
        {
            return $this->adapter->get($key, $callback, $beta, $metadata);
        }
    
        public function delete(string $key): bool
        {
            return $this->adapter->delete($key);
        }
    
        public function hasItem($key): bool
        {
            return $this->adapter->hasItem($key);
        }
    
        public function deleteItem($key): bool
        {
            return $this->adapter->deleteItem($key);
        }
    
        public function deleteItems(array $keys): bool
        {
            return $this->adapter->deleteItems($keys);
        }
    
        public function save(CacheItemInterface $item): bool
        {
            return $this->adapter->save($item);
        }
    
        public function saveDeferred(CacheItemInterface $item): bool
        {
            return $this->adapter->saveDeferred($item);
        }
    
        public function commit(): bool
        {
            return $this->adapter->commit();
        }
    
        public function setLogger(LoggerInterface $logger): void
        {
            $this->adapter->setLogger($logger);
        }
    
        public function reset(): void
        {
            $this->adapter->reset();
        }
    
        private function incCounter(CacheItemInterface $cacheItem): void
        {
            if ($cacheItem->isHit()) {
                $this->statsdAPIClient->increment(self::STATSD_HIT_PREFIX.$cacheItem->getKey());
            } else {
                $this->statsdAPIClient->increment(self::STATSD_MISS_PREFIX.$cacheItem->getKey());
            }
        }
    }
    ```
1. В файл `config/services.yaml` добавляем
    ```yaml
    redis_client:
        class: Redis
        factory: Symfony\Component\Cache\Adapter\RedisAdapter::createConnection
        arguments:
            - '%env(REDIS_DSN)%'

    redis_adapter:
        class: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments:
            - '@redis_client'
            - 'my_app'

    redis_adapter_decorated:
        class: App\Symfony\CountingAdapterDecorator
        arguments:
            - '@redis_adapter'

    App\Service\TweetService:
        arguments:
            $cacheItemPool: '@redis_adapter_decorated'
    ```
1. Выполняем два одинаковых запроса Get Tweet list из Postman-коллекции v5 для прогрева кэша и появления метрик
1. Заходим в Grafana, добавляем новую панель
1. Добавляем на панель метрики `sumSeries(stats_count.my_app.cache.hit.*)` и
   `sumSeries(stats_count.my_app.cache.miss.*)`

## 4. Инвалидация кэша с помощью тэгов

1. В файле `config/services.yaml`
    1. в секции `services` убираем декоратор
        ```yaml
        redis_adapter_decorated:
            class: App\Symfony\CountingAdapterDecorator
            arguments:
                - '@redis_adapter'
        ```
    1. в секции `services.redis_adapter.class` класс на `RedisTagAwareAdapter`
        ```yaml
        class: Symfony\Component\Cache\Adapter\RedisTagAwareAdapter
        ```
    1. в секции `services.App\Service\TweetService.arguments` меняем имя параметра на `$cache` и сервис на
    `redis_adapter`
        ```yaml
        $cache: '@redis_adapter'
        ```
1. В классе `App\Entity\Tweet` исправляем аннотации для полей `createdAt` и `updatedAt`
    ```php
    /**
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="create")
     */
    private DateTime $createdAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="update")
     */
    private DateTime $updatedAt;
    ```
1. В классе `App\Service\TweetService`
    1. Добавляем константу с именем тэга
        ```php
        private const CACHE_TAG = 'tweets';
        ```
    1. Меняем в конструкторе `CacheItemPoolInterface` на `TagAwareCacheInterface`
        ```php
        /** @var TagAwareCacheInterface */
        private $cache;
    
        public function __construct(EntityManagerInterface $entityManager, TagAwareCacheInterface $cache)
        {
            $this->entityManager = $entityManager;
            $this->cache = $cache;
        }
        ```
    1. Исправляем метод `getTweets`
        ```php
        /**
         * @return Tweet[]
         *
         * @throws InvalidArgumentException
         */
        public function getTweets(int $page, int $perPage): array
        {
            /** @var TweetRepository $tweetRepository */
            $tweetRepository = $this->entityManager->getRepository(Tweet::class);
    
            /** @var ItemInterface $organizationsItem */
            return $this->cache->get(
                "tweets_{$page}_{$perPage}",
                function(ItemInterface $item) use ($tweetRepository, $page, $perPage) {
                    $tweets = $tweetRepository->getTweets($page, $perPage);
                    $tweetsSerialized = array_map(static fn(Tweet $tweet) => $tweet->toArray(), $tweets);
                    $item->set($tweetsSerialized);
                    $item->tag(self::CACHE_TAG);

                    return $tweetsSerialized;
                }
            );
        }
        ```
    1. Добавляем метод `saveTweet`
        ```php
        /**
         * @throws InvalidArgumentException
         */
        public function saveTweet(int $authorId, string $text): bool {
            $tweet = new Tweet();
            $userRepository = $this->entityManager->getRepository(User::class);
            $author = $userRepository->find($authorId);
            if (!($author instanceof User)) {
                return false;
            }
            $tweet->setAuthor($author);
            $tweet->setText($text);
            $this->entityManager->persist($tweet);
            $this->entityManager->flush();
            $this->cache->invalidateTags([self::CACHE_TAG]);
    
            return true;
        }
        ```
1. Добавим класс `App\Controller\Api\SaveTweet\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveTweet\v1;
    
    use App\Controller\Common\ErrorResponseTrait;
    use App\Service\TweetService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\RequestParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait, ErrorResponseTrait;
    
        private TweetService $tweetService;
    
        public function __construct(TweetService $tweetService, ViewHandlerInterface $viewHandler)
        {
            $this->tweetService = $tweetService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Post("/api/v1/tweet")
         *
         * @RequestParam(name="authorId", requirements="\d+")
         * @RequestParam(name="text")
         */
        public function saveTweetAction(int $authorId, string $text): Response
        {
            $tweetId = $this->tweetService->saveTweet($authorId, $text);
            [$data, $code] = ($tweetId === null) ? [['success' => false], 400] : [['tweet' => $tweetId], 200];
            return $this->handleView($this->view($data, $code));
        }
    }
    ```
1. Выполняем запрос Post tweet из Postman-коллекции v5, видим ошибку
1. Заходим в контейнер с приложением командой `docker exec -it php sh`
1. В контейнере выполняем команду `php bin/console doctrine:cache:clear-metadata`   
1. Ещё раз выполняем запрос Post tweet из Postman-коллекции v5, видим успешное сохранение
1. В Redis выполняем `flushall`
1. Выполняем несколько запросов Get Tweet list из Postman-коллекции v5 с разными значениями параметров для прогрева кэша
1. Проверяем, что в Redis есть ключи для твитов командой `keys *tweets*`
1. Выполняем запрос Post tweet из Postman-коллекции v5
1. Проверяем, что в Redis удалились все ключи командой `keys *tweets*`
