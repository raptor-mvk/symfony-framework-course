## 1. Подготовка контейнеров

1. Запустить контейнеры командой `docker-compose up -d`
1. Зайти в контейнер `rabbit-mq` командой `docker exec -it rabbit-mq sh` и выполнить в нём команду
   `rabbitmq-plugins enable rabbitmq_consistent_hash_exchange`
1. Перезапустить контейнер с супервизором командой `docker-compose restart supervisor`

## 2. Выносим клиент для графита в отдельный бандл

1. Исправляем секцию `autoload` в `composer.json`
    ```json
    "psr-4": {
        "App\\": "src/",
        "StatsdBundle\\": "src/StatsdBundle"
    }
    ```
1. Заходим в контейнер командой `docker exec -it php sh`
1. В контейнере выполняем команду `composer dump-autoload`
1. Переносим класс `App\Client\StatsdAPIClient` в пространство имён `StatsdBundle`:
    ```php
    <?php
    
    namespace StatsdBundle\Client;
    
    use Domnikl\Statsd\Client;
    use Domnikl\Statsd\Connection\UdpSocket;
    
    class StatsdAPIClient
    {
        private const DEFAULT_SAMPLE_RATE = 1.0;
    
        private Client $client;
    
        public function __construct(string $host, int $port, string $namespace)
        {
            $connection = new UdpSocket($host, $port);
            $this->client = new Client($connection, $namespace);
        }
    
        public function increment(string $key, ?float $sampleRate = null, ?array $tags = null): void
        {
            $this->client->increment($key, $sampleRate ?? self::DEFAULT_SAMPLE_RATE, $tags ?? []);
        }
    }
    ```
1. Создаём файл `StatsdBundle\StatsdBundle`
    ```php
    <?php
    
    namespace StatsdBundle;
    
    use Symfony\Component\HttpKernel\Bundle\Bundle;
    
    class StatsdBundle extends Bundle
    {
    }
    ```
1. В файле `config/services.yaml`
    1. в секции `services` убираем сервис `App\Client\StatsdAPIClient`
    1. в секции `services.App\` добавляем исключение:
        ```yaml
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'
            - '../src/Tests/'
            - '../src/StatsdBundle/'
        ```
1. Создаём файл `src/StatsdBundle/Resources/config/services.yaml`
    ```yaml
    services:
      
      StatsdBundle\Client\StatsdAPIClient:
        arguments:
          - graphite
          - 8125
          - my_app
    ```
1. Создаём класс `StatsdBundle\DependencyInjection\StatsdExtension`
    ```php
    <?php
    
    namespace StatsdBundle\DependencyInjection;
    
    use Symfony\Component\Config\FileLocator;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Extension\Extension;
    use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
    
    class StatsdExtension extends Extension
    {
        public function load(array $configs, ContainerBuilder $container)
        {
            $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('services.yaml');
        }
    }
    ```
1. Подключаем наш бандл в файле `config/bundles.php`
    ```php
    <?php
    
    return [
        Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
        Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
        Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
        Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
        Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
        Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle::class => ['all' => true],
        Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],
        Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
        Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
        FOS\RestBundle\FOSRestBundle::class => ['all' => true],
        JMS\SerializerBundle\JMSSerializerBundle::class => ['all' => true],
        Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
        Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
        Sentry\SentryBundle\SentryBundle::class => ['all' => true],
        OldSound\RabbitMqBundle\OldSoundRabbitMqBundle::class => ['all' => true],
        FOS\ElasticaBundle\FOSElasticaBundle::class => ['all' => true],
        Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
        StatsdBundle\StatsdBundle::class => ['all' => true],
    ];
    ```
1. Выполняем запрос Add user v4 из Postman-коллекции v9 и проверяем, что данные поступают в Graphite
   
## 3. Добавляем конфигурацию в бандл

1. Добавляем класс `StatsdBundle\DependencyInjection\Configuration`
    ```php
    <?php
    
    namespace StatsdBundle\DependencyInjection;
    
    use Symfony\Component\Config\Definition\Builder\TreeBuilder;
    use Symfony\Component\Config\Definition\ConfigurationInterface;
    
    class Configuration implements ConfigurationInterface
    {
        public function getConfigTreeBuilder()
        {
            $treeBuilder = new TreeBuilder('statsd');
            $treeBuilder->getRootNode()
                ->children()
                    ->arrayNode('client')
                        ->children()
                            ->scalarNode('host')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('port')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('namespace')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end();
    
            return $treeBuilder;
        }
    }
    ```
1. В классе `StatsdBundle\DependencyInjection\StatsdExtension` исправляем метод `load`
    ```php
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
    
        $serviceDefinition = $container->getDefinition(StatsdAPIClient::class);
        $serviceDefinition->replaceArgument(0, $config['client']['host']);
        $serviceDefinition->replaceArgument(1, $config['client']['port']);
        $serviceDefinition->replaceArgument(2, $config['client']['namespace']);
    }
    ```
1. Добавляем файл `config/packages/statsd.yaml`
    ```yaml
    statsd:
      client:
        host: graphite
        port: 8125
        namespace: my_app
    ```
1. Исправляем файл `src/StatsdBundle/Resources/config.yaml`
    ```yaml
    services:
    
      StatsdBundle\Client\StatsdAPIClient:
        arguments:
          - localhost
          - 8125
          - some_application
    ```
1. В контейнере очищаем кэш командой `php bin/console cache:clear`
1. Выполняем ещё один запрос Add user v4 из Postman-коллекции v9 и проверяем, что данные поступают в Graphite

## 4. Выносим бандл в отдельный репозиторий

1. Создаём новый репозиторий в git и переносим в него содержимое 'src/StatsdBundle'
1. Убираем загрузку бандла из файла `config/bundles.php`
    ```php
    <?php
    
    return [
        Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
        Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
        Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
        Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
        Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
        Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle::class => ['all' => true],
        Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],
        Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
        Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
        FOS\RestBundle\FOSRestBundle::class => ['all' => true],
        JMS\SerializerBundle\JMSSerializerBundle::class => ['all' => true],
        Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
        Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
        Sentry\SentryBundle\SentryBundle::class => ['all' => true],
        OldSound\RabbitMqBundle\OldSoundRabbitMqBundle::class => ['all' => true],
        FOS\ElasticaBundle\FOSElasticaBundle::class => ['all' => true],
        Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    ];
    ```
1. Создаём в корне репозитория файлы
    1. `README.md`
    1. `LICENSE`
    1. `composer.json` (здесь и далее <package-name> - имя пакета)
        ```json
        {
            "name": "<package-name>",
            "description": "Provides configured StatsdAPIClient",
            "type": "symfony-bundle",
            "license": "MIT",
            "require": {
                "php": "^7.2.5",
                "domnikl/statsd": "^3.0"
            },
            "autoload": {
                "psr-4": {
                    "StatsdBundle\\": ""
                }
            }
        }
        ```
1. Пушим новый проект в репозиторий
1. В исходном проекте в `composer.json`
    1. исправляем секцию `autoload`
        ```json
        "psr-4": {
            "App\\": "src/"
        }
        ```
   1. добавляем секцию `repositories`
       ```json
        "repositories": [
            {
                "type": "vcs",
                "url": "git@github.com:<package-name>.git"
            }
       ]
       ```
1. Выполняем команду `composer requre <package-name>:dev-master`
1. Выполняем ещё один запрос Add user v4 из Postman-коллекции v9 и проверяем, что данные всё ещё поступают в Graphite

## 5. Отделяем работу с лентой в отдельный бандл

1. Исправляем секцию `autoload` в `composer.json`
    ```json
    "psr-4": {
        "App\\": "src/",
        "FeedBundle\\": "src/FeedBundle"
    }
    ```
1. Выполняем команду `composer dump-autoload`
1. Создаём файл `FeedBundle\FeedBundle`
    ```php
    <?php
    
    namespace FeedBundle;
    
    use Symfony\Component\HttpKernel\Bundle\Bundle;
    
    class FeedBundle extends Bundle
    {
    }
    ```
1. Создаём класс `FeedBundle\DependencyInjection\FeedExtension`
    ```php
    <?php
    
    namespace FeedBundle\DependencyInjection;
    
    use Symfony\Component\Config\FileLocator;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Extension\Extension;
    use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
    
    class FeedExtension extends Extension
    {
        public function load(array $configs, ContainerBuilder $container)
        {
            $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('services.yaml');
        }
    }
    ```
1. Подключаем наш бандл в файле `config/bundles.php`
    ```php
    <?php
    
    return [
        Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
        Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
        Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
        Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
        Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
        Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle::class => ['all' => true],
        Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],
        Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
        Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
        FOS\RestBundle\FOSRestBundle::class => ['all' => true],
        JMS\SerializerBundle\JMSSerializerBundle::class => ['all' => true],
        Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
        Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
        Sentry\SentryBundle\SentryBundle::class => ['all' => true],
        OldSound\RabbitMqBundle\OldSoundRabbitMqBundle::class => ['all' => true],
        FOS\ElasticaBundle\FOSElasticaBundle::class => ['all' => true],
        Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
        StatsdBundle\StatsdBundle::class => ['all' => true],
        FeedBundle\FeedBundle::class => ['all' => true],
    ];
    ```
1. В файле `config/services.yaml`
    1. в секции `services.App\` добавляем исключение:
        ```yaml
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'
            - '../src/Tests/'
            - '../src/FeedBundle/'
        ```
    1. удаляем сервисы для `App\Consumer\UpdateFeedConsumer`
    1. исправляем описание сервиса `App\Service\AsyncService`
        ```yaml
        App\Service\AsyncService:
            calls:
                - ['registerProducer', [!php/const App\Service\AsyncService::ADD_FOLLOWER, '@old_sound_rabbit_mq.add_follower_producer']]
                - ['registerProducer', [!php/const App\Service\AsyncService::PUBLISH_TWEET, '@old_sound_rabbit_mq.publish_tweet_producer']]
                - ['registerProducer', [!php/const App\Service\AsyncService::UPDATE_FEED, '@old_sound_rabbit_mq.update_feed_producer']]
        ```
1. В файле `config/packages/doctrine.yaml` добавляем в секцию `doctrine.orm.mappings`
    ```yaml
    FeedBundle:
        is_bundle: true
        type: annotation
        dir: 'Entity'
        prefix: 'FeedBundle\Entity'
        alias: FeedBundle
    ``` 
1. В файле `config/packages/old_sound_rabbit_mq.yaml` исправляем описание консьюмеров с префиксом `update_feed`
    ```yaml
    update_feed_0:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_0', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer0
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_1:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_1', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer1
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_2:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_2', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer2
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_3:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_3', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer3
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_4:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_4', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer4
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_5:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_5', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer5
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_6:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_6', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer6
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_7:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_7', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer7
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_8:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_8', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer8
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    update_feed_9:
        connection: default
        exchange_options: {name: 'old_sound_rabbit_mq.update_feed', type: x-consistent-hash}
        queue_options: {name: 'old_sound_rabbit_mq.consumer.update_feed_9', routing_key: '1'}
        callback: FeedBundle\Consumer\UpdateFeed\Consumer9
        idle_timeout: 300
        idle_timeout_exit_code: 0
        graceful_max_execution:
            timeout: 1800
            exit_code: 0
        qos_options: {prefetch_size: 0, prefetch_count: 20, global: false}
    ```
1. Переносим в пространство имён `FeedBundle` класс `App\DTO\SendNotificationDTO`
1. Создаём класс `FeedBundle\DTO\TweetDTO`
    ```php
    <?php
    
    namespace FeedBundle\DTO;
    
    class TweetDTO
    {
        private array $payload;
    
        public function __construct(int $id, string $author, string $text, string $createdAt)
        {
            $this->payload = [
                'id' => $id,
                'author' => $author,
                'text' => $text,
                'createdAt' => $createdAt
            ];
        }
    
        public function getPayload(): array
        {
            return $this->payload;
        }
    
        public function getText(): string
        {
            return $this->payload['text'];
        }
    }
     ```
1. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Consumer\UpdateFeedConsumer\Input\Message`
    ```php
    <?php
    
    namespace FeedBundle\Consumer\UpdateFeed\Input;
    
    use FeedBundle\DTO\TweetDTO;
    use Symfony\Component\Validator\Constraints;
    
    final class Message
    {
        private TweetDTO $tweetDTO;
    
        /**
         * @Constraints\Regex("/^\d+$/")
         */
        private int $followerId;
    
        private string $preferred;
    
    
        public static function createFromQueue(string $messageBody): self
        {
            $message = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
            $result = new self();
            $result->tweetDTO = new TweetDTO((int)$message['id'], $message['author'], $message['text'], $message['createdAt']);
            $result->followerId = $message['followerId'];
            $result->preferred = $message['preferred'];
    
            return $result;
        }
    
        public function getTweetDTO(): TweetDTO
        {
            return $this->tweetDTO;
        }
    
        public function getFollowerId(): int
        {
            return $this->followerId;
        }
    
        public function getPreferred(): string
        {
            return $this->preferred;
        }
    }
     ```
1. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Entity\Feed`
    ```php
    <?php
    
    namespace FeedBundle\Entity;
    
    use DateTime;
    use Doctrine\ORM\Mapping as ORM;
    use Gedmo\Mapping\Annotation as Gedmo;
    
    /**
     * @ORM\Table(
     *     name="feed",
     *     uniqueConstraints={@ORM\UniqueConstraint(columns={"reader_id"})}
     * )
     * @ORM\Entity
     */
    class Feed
    {
        /**
         * @ORM\Column(name="id", type="bigint", unique=true)
         * @ORM\Id
         * @ORM\GeneratedValue(strategy="IDENTITY")
         */
        private int $id;
    
        /**
         * @ORM\Column(name="reader_id", type="bigint", nullable=false)
         */
        private $readerId;
    
        /**
         * @ORM\Column(type="json", nullable=true)
         */
        private ?array $tweets;
    
        /**
         * @ORM\Column(name="created_at", type="datetime", nullable=false)
         * @Gedmo\Timestampable(on="create")
         */
        private DateTime $createdAt;
    
        /**
         * @ORM\Column(name="updated_at", type="datetime", nullable=false)
         * @Gedmo\Timestampable(on="update")
         */
        private DateTime $updatedAt;
    
        public function getId(): int
        {
            return $this->id;
        }
    
        public function setId(int $id): void
        {
            $this->id = $id;
        }
    
        public function getReaderId(): int
        {
            return $this->readerId;
        }
    
        public function setReaderId(int $readerId): void
        {
            $this->readerId = $readerId;
        }
    
        public function getTweets(): ?array
        {
            return $this->tweets;
        }
    
        public function setTweets(?array $tweets): void
        {
            $this->tweets = $tweets;
        }
    
        public function getCreatedAt(): DateTime {
            return $this->createdAt;
        }
    
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    }
     ```
1. Копируем в пространство имён `FeedBundle` и исправляем класс `App\Service\AsyncService`
    ```php
    <?php
    
    namespace FeedBundle\Service;
    
    use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
    
    class AsyncService
    {
        public const SEND_NOTIFICATION = 'send_notification';
    
        /** @var ProducerInterface[] */
        private array $producers;
    
        public function __construct()
        {
            $this->producers = [];
        }
    
        public function registerProducer(string $producerName, ProducerInterface $producer): void
        {
            $this->producers[$producerName] = $producer;
        }
    
        public function publishToExchange(string $producerName, string $message, ?string $routingKey = null, ?array $additionalProperties = null): bool
        {
            if (isset($this->producers[$producerName])) {
                $this->producers[$producerName]->publish($message, $routingKey ?? '', $additionalProperties ?? []);
    
                return true;
            }
    
            return false;
        }
    
        public function publishMultipleToExchange(string $producerName, array $messages, ?string $routingKey = null, ?array $additionalProperties = null): int
        {
            $sentCount = 0;
            if (isset($this->producers[$producerName])) {
                foreach ($messages as $message) {
                    $this->producers[$producerName]->publish($message, $routingKey ?? '', $additionalProperties ?? []);
                    $sentCount++;
                }
    
                return $sentCount;
            }
    
            return $sentCount;
        }
    }
     ```
1. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Service\FeedService`
    ```php
    <?php
    
    namespace FeedBundle\Service;
    
    use FeedBundle\DTO\TweetDTO;
    use FeedBundle\Entity\Feed;
    use Doctrine\ORM\EntityManagerInterface;
    
    class FeedService
    {
        private EntityManagerInterface $entityManager;
    
        public function __construct(EntityManagerInterface $entityManager, )
        {
            $this->entityManager = $entityManager;
        }
    
        public function getFeed(int $userId, int $count): array
        {
            $feed = $this->getFeedFromRepository($userId);
    
            return $feed === null ? [] : array_slice($feed->getTweets(), -$count);
        }
    
        public function putTweet(TweetDTO $tweetDTO, int $userId): bool
        {
            $feed = $this->getFeedFromRepository($userId);
            if ($feed === null) {
                return false;
            }
            $tweets = $feed->getTweets();
            $tweets[] = $tweetDTO->getPayload();
            $feed->setTweets($tweets);
            $this->entityManager->persist($feed);
            $this->entityManager->flush();
    
            return true;
        }
    
        private function getFeedFromRepository(int $userId): ?Feed
        {
            $feedRepository = $this->entityManager->getRepository(Feed::class);
            $feed = $feedRepository->findOneBy(['readerId' => $userId]);
            if (!($feed instanceof Feed)) {
                $feed = new Feed();
                $feed->setReaderId($userId);
                $feed->setTweets([]);
            }
    
            return $feed;
        }
    }
     ```
1. Переносим в пространство имён `FeedBundle` и исправляем класс `App\Consumer\UpdateFeedConsumer\Consumer`
    ```php
    <?php
    
    namespace FeedBundle\Consumer\UpdateFeed;
    
    use StatsdBundle\Client\StatsdAPIClient;
    use FeedBundle\Consumer\UpdateFeed\Input\Message;
    use FeedBundle\DTO\SendNotificationDTO;
    use FeedBundle\Service\AsyncService;
    use FeedBundle\Service\FeedService;
    use Doctrine\ORM\EntityManagerInterface;
    use JsonException;
    use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
    use PhpAmqpLib\Message\AMQPMessage;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    
    class Consumer implements ConsumerInterface
    {
        private EntityManagerInterface $entityManager;
    
        private ValidatorInterface $validator;
    
        private FeedService $feedService;
    
        private AsyncService $asyncService;
    
        private StatsdAPIClient $statsdAPIClient;
    
        private string $key;
    
        public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, FeedService $feedService, AsyncService $asyncService, StatsdAPIClient $statsdAPIClient, string $key)
        {
            $this->entityManager = $entityManager;
            $this->validator = $validator;
            $this->feedService = $feedService;
            $this->asyncService = $asyncService;
            $this->statsdAPIClient = $statsdAPIClient;
            $this->key = $key;
        }
    
        public function execute(AMQPMessage $msg): int
        {
            try {
                $message = Message::createFromQueue($msg->getBody());
                $errors = $this->validator->validate($message);
                if ($errors->count() > 0) {
                    return $this->reject((string)$errors);
                }
            } catch (JsonException $e) {
                return $this->reject($e->getMessage());
            }
            
            $tweetDTO = $message->getTweetDTO();
            $this->feedService->putTweet($tweetDTO, $message->getFollowerId());
            $notificationMessage = (new SendNotificationDTO($message->getFollowerId(), $tweetDTO->getText()))->toAMQPMessage();
            $this->asyncService->publishToExchange(
                AsyncService::SEND_NOTIFICATION,
                $notificationMessage,
                $message->getPreferred()
            );
    
            $this->statsdAPIClient->increment($this->key);
            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
    
            return self::MSG_ACK;
        }
    
        private function reject(string $error): int
        {
            echo "Incorrect message: $error";
    
            return self::MSG_REJECT;
        }
    }
     ```
1. Создаём класс `FeedBundle\Facade\FeedFacade`
    ```php
    <?php
    
    namespace FeedBundle\Facade;
    
    use FeedBundle\Service\FeedService;
    
    class FeedFacade
    {
        private FeedService $feedService;
    
        public function __construct(FeedService $feedService)
        {
            $this->feedService = $feedService;
        }
    
        public function getFeed(int $userId, int $count): array
        {
            return $this->feedService->getFeed($userId, $count);
        }
    }
    ```
1. Исправляем класс `App\Consumer\PublishTweetConsumer\Output\UpdateFeedMessage`
    ```
    <?php
    
    namespace App\Consumer\PublishTweet\Output;
    
    use App\Entity\Tweet;
    use App\Entity\User;
    
    final class UpdateFeedMessage
    {
        private array $payload;
    
        public function __construct(Tweet $tweet, User $follower)
        {
            $this->payload = array_merge($tweet->toFeed(), ['followerId' => $follower->getId(), 'preferred' => $follower->getPreferred()]);
        }
    
        public function toAMQPMessage(): string
        {
            return json_encode($this->payload, JSON_THROW_ON_ERROR, 512);
        }
    }
    ```
1. В класс `App\Service\SubscriptionService` добавляем новый метод `getFollowers`
    ```php
    /**
     * @return User[]
     */
    public function getFollowers(int $authorId): array
    {
        $subscriptions = $this->getSubscriptionsByAuthorId($authorId);
        $mapper = static function(Subscription $subscription) {
            return $subscription->getFollower();
        };

        return array_map($mapper, $subscriptions);
    }
    ```
1. Исправляем класс `App\Consumer\PublishTweet\Consumer`
    ```php
    <?php
    
    namespace App\Consumer\PublishTweet;
    
    use App\Consumer\PublishTweet\Input\Message;
    use App\Consumer\PublishTweet\Output\UpdateFeedMessage;
    use App\Entity\Tweet;
    use App\Service\AsyncService;
    use App\Service\SubscriptionService;
    use Doctrine\ORM\EntityManagerInterface;
    use JsonException;
    use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
    use PhpAmqpLib\Message\AMQPMessage;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    
    class Consumer implements ConsumerInterface
    {
        private EntityManagerInterface $entityManager;
    
        private ValidatorInterface $validator;
    
        private SubscriptionService $subscriptionService;
    
        private AsyncService $asyncService;
    
        public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, SubscriptionService $subscriptionService, AsyncService $asyncService)
        {
            $this->entityManager = $entityManager;
            $this->validator = $validator;
            $this->subscriptionService = $subscriptionService;
            $this->asyncService = $asyncService;
        }
    
        public function execute(AMQPMessage $msg): int
        {
            try {
                $message = Message::createFromQueue($msg->getBody());
                $errors = $this->validator->validate($message);
                if ($errors->count() > 0) {
                    return $this->reject((string)$errors);
                }
            } catch (JsonException $e) {
                return $this->reject($e->getMessage());
            }
    
            $tweetRepository = $this->entityManager->getRepository(Tweet::class);
            $tweet = $tweetRepository->find($message->getTweetId());
            if (!($tweet instanceof Tweet)) {
                return $this->reject(sprintf('Tweet ID %s was not found', $message->getTweetId()));
            }
    
            $followers = $this->subscriptionService->getFollowers($tweet->getAuthor()->getId());
    
            foreach ($followers as $follower) {
                $message = (new UpdateFeedMessage($tweet, $follower))->toAMQPMessage();
                $this->asyncService->publishToExchange(AsyncService::UPDATE_FEED, $message, (string)$follower->getId());
            }
    
            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
    
            return self::MSG_ACK;
        }
    
        private function reject(string $error): int
        {
            echo "Incorrect message: $error";
    
            return self::MSG_REJECT;
        }
    }
     ```
1. В классе `App\Service\AsyncService` убираем константу `SEND_NOTIFICATION`
1. Исправляем класс `App\Controller\Api\SaveTweet\v1`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveTweet\v1;
    
    use App\Controller\Common\ErrorResponseTrait;
    use App\Service\AsyncService;
    use App\Service\TweetService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\RequestParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\View;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait, ErrorResponseTrait;
    
        private TweetService $tweetService;
        
        private AsyncService $asyncService;
    
        public function __construct(TweetService $tweetService, AsyncService $asyncService, ViewHandlerInterface $viewHandler)
        {
            $this->tweetService = $tweetService;
            $this->asyncService = $asyncService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Post("/api/v1/tweet")
         *
         * @RequestParam(name="authorId", requirements="\d+")
         * @RequestParam(name="text")
         * @RequestParam(name="async", requirements="0|1", nullable=true)
         */
        public function saveTweetAction(int $authorId, string $text, ?int $async): Response
        {
            $tweet = $this->tweetService->saveTweet($authorId, $text);
            $success = $tweet !== null;
            if ($success) {
                if ($async === 1) {
                    $this->asyncService->publishToExchange(AsyncService::PUBLISH_TWEET, $tweet->toAMPQMessage());
                } else {
                    return $this->handleView(View::create(['message' => 'Sync post is no longer supported'], 400));
                }
            }
            $code = $success ? 200 : 400;
    
            return $this->handleView($this->view(['success' => $success], $code));
        }
    }
     ```
1. Исправляем класс `App\Controller\Api\v1\FeedController`
    ```
    <?php
    
    namespace App\Controller\Api\GetFeed\v1;
    
    use FeedBundle\Facade\FeedFacade;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\View\View;
    
    final class Controller
    {
        /** @var int */
        private const DEFAULT_FEED_SIZE = 20;
    
        private FeedFacade $feedFacade;
    
        public function __construct(FeedFacade $feedFacade)
        {
            $this->feedFacade = $feedFacade;
        }
    
        /**
         * @Rest\Get("/api/v1/get-feed")
         *
         * @Rest\QueryParam(name="userId", requirements="\d+")
         * @Rest\QueryParam(name="count", requirements="\d+", nullable=true)
         */
        public function getFeedAction(int $userId, ?int $count = null): View
        {
            $count = $count ?? self::DEFAULT_FEED_SIZE;
            $tweets = $this->feedFacade->getFeed($userId, $count);
            $code = empty($tweets) ? 204 : 200;
    
            return View::create(['tweets' => $tweets], $code);
        }
    }
     ```
1. Создаём файл `src/FeedBundle/Resources/config/services.yaml`
    ```yaml
    services:
      _defaults:
        autowire: true
        autoconfigure: true
    
      FeedBundle\:
        resource: '../../*'
        exclude: '../../{DependencyInjection,Entity}'
    
      FeedBundle\Service\AsyncService:
        calls:
          - ['registerProducer', [!php/const FeedBundle\Service\AsyncService::SEND_NOTIFICATION, '@old_sound_rabbit_mq.send_notification_producer']]
      
      FeedBundle\Consumer\UpdateFeed\Consumer0:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_0'
    
      FeedBundle\Consumer\UpdateFeed\Consumer1:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_1'
    
      FeedBundle\Consumer\UpdateFeed\Consumer2:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_2'
    
      FeedBundle\Consumer\UpdateFeed\Consumer3:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_3'
    
      FeedBundle\Consumer\UpdateFeed\Consumer4:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_4'
    
      FeedBundle\Consumer\UpdateFeed\Consumer5:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_5'
    
      FeedBundle\Consumer\UpdateFeed\Consumer6:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_6'
    
      FeedBundle\Consumer\UpdateFeed\Consumer7:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_7'
    
      FeedBundle\Consumer\UpdateFeed\Consumer8:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_8'
    
      FeedBundle\Consumer\UpdateFeed\Consumer9:
        class: FeedBundle\Consumer\UpdateFeed\Consumer
        arguments:
          $key: 'update_feed_9'    
    ```
1. Очищаем кэш из контейнера командой `php bin/console cache:clear`
1. Перезапускаем supervisor командой `docker-compose restart supervisor`
1. Выполняем запрос Post tweet v4 из Postman-коллекции v9 и проверяем, что 
    1. данные поступают в Graphite
    1. отправляются сообщения в очереди с префиксом `send_notification`
1. Выполняем запрос Get feed из Postman-коллекции v9 и видим, что лента возвращается

## 6. Переходим на общение по HTTP

1. Устанавливаем пакет `guzzlehttp/guzzle`
1. Создаём класс `App\Client\FeedClient`
    ```php
    <?php
    
    namespace App\Client;
    
    use GuzzleHttp\Client;
    
    class FeedClient
    {
        private Client $client;

        private string $baseUrl;
    
        public function __construct(Client $client, string $baseUrl)
        {
            $this->client = $client;
            $this->baseUrl = $baseUrl;
        }
    
        public function getFeed(int $userId, int $count): array
        {
            $response = $this->client->get("{$this->baseUrl}/server-api/v1/get-feed", [
                'query' => [
                    'userId' => $userId,
                    'count' => $count,
                ],
            ]);
            $responseData = json_decode($response->getBody(), true);
    
            return $responseData['tweets'];
        }
    }
    ```
1. Создаём класс `FeedBundle\Controller\ServerApi\GetFeed\v1\Controller`
    ```php
    <?php
    
    namespace FeedBundle\Controller\ServerApi\GetFeed\v1;
    
    use FeedBundle\Service\FeedService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\View\View;
    
    final class Controller
    {
        /** @var int */
        private const DEFAULT_FEED_SIZE = 20;
    
        private FeedService $feedService;
    
        public function __construct(FeedService $feedService)
        {
            $this->feedService = $feedService;
        }
    
        /**
         * @Rest\Get("/v1/get-feed")
         *
         * @Rest\QueryParam(name="userId", requirements="\d+")
         * @Rest\QueryParam(name="count", requirements="\d+", nullable=true)
         */
        public function getFeedAction(int $userId, ?int $count = null): View
        {
            $count = $count ?? self::DEFAULT_FEED_SIZE;
            $tweets = $this->feedService->getFeed($userId, $count);
            $code = empty($tweets) ? 204 : 200;
    
            return View::create(['tweets' => $tweets], $code);
        }
    }
     ```
1. Переносим класс `FeedBundle\Facade\FeedFacade` в пространство имён `App` и исправляем
    ```
    <?php
    
    namespace App\Facade;
    
    use App\Client\FeedClient;
    
    class FeedFacade
    {
        /** @var FeedClient */
        private $feedClient;
    
        public function __construct(FeedClient $feedClient)
        {
            $this->feedClient = $feedClient;
        }
    
        public function getFeed(int $userId, int $count): array
        {
            return $this->feedClient->getFeed($userId, $count);
        }
    }
    ```
1. В классе `App\Controller\Api\GetFeed\v1\Controller` исправляем use-выражение для `FeedFacade` 
1. В файле `config/services.yaml` добавляем сервисы
    ```
    feed_http_client:
        class: GuzzleHttp\Client
    
    App\Client\FeedClient:
        arguments:
            - '@feed_http_client'
            - 'http://nginx:80'
    ```
1. В файл `config/routes.yaml` добавляем
    ```yaml
    server_api:
      resource: "@FeedBundle/Controller/ServerApi"
      type: annotation
      prefix: /server-api
    ```
1. В файл `config\packages\fos_rest.yaml` в секцию `format_listener.rules` добавляем правило
    ```yaml
    - { path: ^/server-api, prefer_extension: true, fallback_format: json, priorities: [ json, html ] }
    ```
1. В файле `src/FeedBundle/Resources/config/services.yaml` добавляем тэги на контроллер
    ```yaml
    FeedBundle\Controller\:
      resource: '../../Controller/'
      tags: ['controller.service_arguments']
    ```
1. Выполняем запрос Get feed из Postman-коллекции v9 и видим, что лента возвращается
   