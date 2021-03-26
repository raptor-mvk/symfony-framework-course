Запускаем контейнеры командой `docker-compose up -d`

## 1. Добавляем функционал для асинхронной обработки

1. В классе `App\Entity\Subscription` добавляем аннотации для полей `createdAt` и `updatedAt`
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
1. Добавляем класс `App\Service\SubscriptionService`
    ```php
    <?php
    
    namespace App\Service;
    
    use App\DTO\UserDTO;
    use App\Entity\Subscription;
    use App\Entity\User;
    use Doctrine\ORM\EntityManagerInterface;
    
    class SubscriptionService
    {
        /** @var EntityManagerInterface */
        private $entityManager;
        /** @var UserService */
        private $userService;
    
        public function __construct(EntityManagerInterface $entityManager, UserService $userService)
        {
            $this->userService = $userService;
            $this->entityManager = $entityManager;
        }
    
        public function subscribe(int $authorId, int $followerId): bool
        {
            $userRepository = $this->entityManager->getRepository(User::class);
            $author = $userRepository->find($authorId);
            if (!($author instanceof User)) {
                return false;
            }
            $follower = $userRepository->find($followerId);
            if (!($follower instanceof User)) {
                return false;
            }
    
            $subscription = new Subscription();
            $subscription->setAuthor($author);
            $subscription->setFollower($follower);
            $this->entityManager->persist($subscription);
            $this->entityManager->flush();
    
            return true;
        }
    
        public function addFollowers(User $user, string $followerLogin, int $count): int
        {
            $createdFollowers = 0;
            for ($i = 0; $i < $count; $i++) {
                $login = "{$followerLogin}_#$i";
                $password = $followerLogin;
                $age = $i;
                $isActive = true;
                $data = compact('login', 'password', 'age', 'isActive');
                $followerId = $this->userService->saveUser(new User(), new UserDTO($data));
                if ($followerId !== null) {
                    $this->subscribe($user->getId(), $followerId);
                    $createdFollowers++;
                }
            }
    
            return $createdFollowers;
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\AddFollowers\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\AddFollowers\v1;
    
    use App\Service\SubscriptionService;
    use App\Service\UserService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\RequestParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait;
    
        private SubscriptionService $subscriptionService;
    
        private UserService $userService;
    
        public function __construct(SubscriptionService $subscriptionService, UserService $userService, ViewHandlerInterface $viewHandler)
        {
            $this->subscriptionService = $subscriptionService;
            $this->userService = $userService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Post("/api/v1/add-followers")
         *
         * @RequestParam(name="userId", requirements="\d+")
         * @RequestParam(name="followersLogin")
         * @RequestParam(name="count", requirements="\d+")
         */
        public function addFollowersAction(int $userId, string $followersLogin, int $count): Response
        {
            $user = $this->userService->findUserById($userId);
            if ($user !== null) {
                $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
                $view = $this->view(['created' => $createdFollowers], 200);
            } else {
                $view = $this->view(['success' => false], 404);
            }
    
            return $this->handleView($view);
        }
    }
    ```
1. Заходим в контейнер командой `docker exec -it php sh`
1. Сбрасываем кэш метаданных Doctrine командой `php bin/console doctrine:cache:clear-metadata`
1. Выполняем запрос Add followers из Postman-коллекции v6, чтобы добавить 100 фолловеров

## 2. Установка RabbitMQ и rabbitmq-bundle

1. Устанавливаем пакет `php-amqplib/rabbitmq-bundle`
1. В файл `docker-compose.yml` добавляем новый сервис:
    ```yaml
    rabbitmq:
      image: rabbitmq:3.7.5-management
      working_dir: /app
      hostname: rabbit-mq
      container_name: 'rabbit-mq'
      ports:
        - 15672:15672
        - 5672:5672
      environment:
        RABBITMQ_DEFAULT_USER: user
        RABBITMQ_DEFAULT_PASS: password
    ```
1. Добавляем параметры для подключения к RabbitMQ в файл `.env`
    ```shell
    RABBITMQ_URL=amqp://user:password@rabbit-mq:5672
    RABBITMQ_VHOST=/
    ```
1. Перезапускаем контейнеры
    ```shell
    docker-compose stop
    docker-compose up -d
    ```
1. Заходим по адресу `localhost:15672` и авторизуемся с указанными реквизитами

## 3. Переводим на асинхронное взаимодействие

1. В файл `config/packages/old_sound_rabbit_mq.yaml` добавляем описание продюсера и консьюмера
    ```yaml
    producers:
     add_followers:
       connection: default
       exchange_options: {name: 'old_sound_rabbit_mq.add_followers', type: direct}
    
    consumers:
     add_followers:
       connection: default
       exchange_options: {name: 'old_sound_rabbit_mq.add_followers', type: direct}
       queue_options: {name: 'old_sound_rabbit_mq.consumer.add_followers'}
       callback: App\Consumer\AddFollowers\Consumer
       idle_timeout: 300
       idle_timeout_exit_code: 0
       graceful_max_execution:
         timeout: 1800
         exit_code: 0
       qos_options: {prefetch_size: 0, prefetch_count: 1, global: false}
    ```
1. Добавляем класс `App\Consumer\AddFollowers\Input\Message`
    ```php
    <?php
    
    namespace App\Consumer\AddFollowers\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    final class Message
    {
        /**
         * @Assert\Type("numeric")
         */
        private int $userId;
    
        /**
         * @Assert\Type("string")
         * @Assert\Length(max="32")
         */
        private string $followerLogin;
    
        /**
         * @Assert\Type("numeric")
         */
        private int $count;
    
        public static function createFromQueue(string $messageBody): self
        {
            $message = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
            $result = new self();
            $result->userId = $message['userId'];
            $result->followerLogin = $message['followerLogin'];
            $result->count = $message['count'];
    
            return $result;
        }
    
        public function getUserId(): int
        {
            return $this->userId;
        }
    
        public function getFollowerLogin(): string
        {
            return $this->followerLogin;
        }
    
        public function getCount(): int
        {
            return $this->count;
        }
    }
    ```
1. Добавляем класс `App\Consumer\AddFollowers\Consumer`
    ```php
    <?php
    
    namespace App\Consumer\AddFollowers;
    
    use App\Consumer\AddFollowers\Input\Message;
    use App\Entity\User;
    use App\Service\SubscriptionService;
    use Doctrine\ORM\EntityManagerInterface;
    use Exception;
    use JsonException;
    use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
    use PhpAmqpLib\Message\AMQPMessage;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    
    final class Consumer implements ConsumerInterface
    {
        private EntityManagerInterface $entityManager;
    
        private ValidatorInterface $validator;
    
        private SubscriptionService $subscriptionService;
    
        public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, SubscriptionService $subscriptionService)
        {
            $this->entityManager = $entityManager;
            $this->validator = $validator;
            $this->subscriptionService = $subscriptionService;
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
    
            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->find($message->getUserId());
            if (!($user instanceof User)) {
                return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
            }
    
            $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());
    
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
1. Добавляем класс `App\Service\AsyncService`
    ```php
    <?php
    
    namespace App\Service;
    
    use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
    
    class AsyncService
    {
        public const ADD_FOLLOWER = 'add_follower';
    
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
    }
    ```
1. Добавляем класс `App\DTO\AddFollowersDTO`
    ```php
    <?php
    
    namespace App\DTO;
    
    class AddFollowersDTO
    {
        private array $payload;
    
        public function __construct(int $userId, string $followerLogin, int $count)
        {
            $this->payload = ['userId' => $userId, 'followerLogin' => $followerLogin, 'count' => $count];
        }
    
        public function toAMQPMessage(): string
        {
            return json_encode($this->payload);
        }
    }
    ```
1. В классе `App\Controller\Api\AddFollowers\v1\Controller`
    1. Добавляем параметр типа `AsyncService` в конструктор
    1. Исправляем метод `addFollowersAction`
        ```php
        /**
         * @Rest\Post("/api/v1/add-followers")
         *
         * @RequestParam(name="userId", requirements="\d+")
         * @RequestParam(name="followersLogin")
         * @RequestParam(name="count", requirements="\d+")
         * @RequestParam(name="async", requirements="0|1")
         */
        public function addFollowersAction(int $userId, string $followersLogin, int $count, int $async): Response
        {
            $user = $this->userService->findUserById($userId);
            if ($user !== null) {
                if ($async === 0) {
                    $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
                    $view = $this->view(['created' => $createdFollowers], 200);
                } else {
                    $message = (new AddFollowersDTO($userId, $followersLogin, $count))->toAMQPMessage();
                    $result = $this->asyncService->publishToExchange(AsyncService::ADD_FOLLOWER, $message);
                    $view = $this->view(['success' => $result], $result ? 200 : 500);
                }
            } else {
                $view = $this->view(['success' => false], 404);
            }
       
            return $this->handleView($view);
        }
        ```
1. В файл `config/services.yaml` добавляем новый сервис
    ```yaml
    App\Service\AsyncService:
        calls:
            - ['registerProducer', [!php/const App\Service\AsyncService::ADD_FOLLOWER, '@old_sound_rabbit_mq.add_followers_producer']]
    ```
1. Заходим в контейнер командой `docker exec -it php sh`
1. Запускаем консьюмер командой `php bin/console rabbitmq:consumer add_followers -m 100`
1. Выполняем запрос Add followers из Postman-коллекции v6 с параметром `async` = 1, видим в интерфейсе RabbitMQ
   пришедшее сообщение и то, что в БД добавились фолловеры

## 4. Эмулируем многократную доставку

1. В классе `App\Consumer\AddFollowers\Consumer` в методе `execute` безусловно выбрасываем исключение после добавления
   фолловеров
    ```php
    throw new Exception('Something happens');
    ```
1. Перезапускаем консьюмер из контейнера командой `php bin/console rabbitmq:consumer add_followers -m 100`
1. Выполняем запрос Add followers из Postman-коллекции v6 с параметром `async` = 1, видим в интерфейсе RabbitMQ
   пришедшее сообщение и то, что оно не обработалось, хотя в БД добавились фолловеры
1. В консоли видим сообщение об ошибке от консьюмера, перезапускаем его командой
   `php bin/console rabbitmq:consumer add_followers -m 100` и видим ошибку уже добавления в БД из-за нарушения
   уникальности логина
   
## 5. Исправляем проблему многократной доставки

1. В классе `App\Consumer\AddFollowers\Consumer` исправляем метод `execute`
    ```php
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
        
        try {
            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->find($message->getUserId());
            if (!($user instanceof User)) {
                return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
            }

            $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());
            throw new Exception('Something happens');

            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
        } catch (Throwable $e) {
            $this->reject($e->getMessage());
        }

        return self::MSG_ACK;
    }
    ```
1. Перезапускаем консьюмер из контейнера командой `php bin/console rabbitmq:consumer add_followers -m 100`
1. Видим сообщение об ошибке, но в интерфейсе RabbitMQ сообщение из очереди уходит
1. Останавливаем консьюмер в контейнере

## 6. Эмулируем "убийственную" задачу

1. В классе `App\Consumer\AddFollowers\Consumer` исправляем метод `execute`
    ```php
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

        try {
            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->find($message->getUserId());
            if (!($user instanceof User)) {
                return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
            }

            if ($message->getCount() === 5) {
                sleep(1000);
            }

            $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());

            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
        } catch (Throwable $e) {
            $this->reject($e->getMessage());
        }

        return self::MSG_ACK;
    }
    ```
1. Выполняем несколько запрос Add followers из Postman-коллекции v6 с параметром `async` = 1 и разными значениями
   `count`: сначала не равными 5, потом 5, потом ещё какие-нибудь не равные 5.
1. Перезапускаем консьюмер из контейнера командой `php bin/console rabbitmq:consumer add_followers -m 100`
1. Видим, что до "убийственной" задачи сообщения разобрались, но затем всё остановилось.
1. Останавливаем консьюмер и запускаем два параллельных консьюмера командой
    ```shell
    php bin/console rabbitmq:consumer add_followers -m 100 &
    php bin/console rabbitmq:consumer add_followers -m 100 &
    ```
1. Видим, что разобрались все сообщения, кроме "убийственной" задачи
1. Останавливаем консьюмеры командой `kill` c PID процессов консьюмеров и делаем Purge messages из очереди

## 7. Работа с большим количеством сообщений

1. В классе `App\Consumer\AddFollowers\Consumer` исправляем метод `execute`
    ```php
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

        try {
            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->find($message->getUserId());
            if (!($user instanceof User)) {
                return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
            }

            $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());
            sleep(1);

            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
        } catch (Throwable $e) {
            $this->reject($e->getMessage());
        }

        return self::MSG_ACK;
    }
    ```
1. В класс `App\Service\SubscriptionService` добавляем метод `getFollowersMessages`
    ```php
    /**
     * @return string[]
     */
    public function getFollowersMessages(User $user, string $followerLogin, int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = (new AddFollowersDTO($user->getId(), "$followerLogin #$i", 1))->toAMQPMessage();
        }

        return $result;
    }
    ```
1. В класс `App\Service\AsyncService` добавляем метод `publishMultipleToExchange`
    ```php
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
    ```
1. В классе `App\Controller\Api\AddFollowers\v1\Controller`
    ```php
    /**
     * @Rest\Post("/api/v1/add-followers")
     *
     * @RequestParam(name="userId", requirements="\d+")
     * @RequestParam(name="followersLogin")
     * @RequestParam(name="count", requirements="\d+")
     * @RequestParam(name="async", requirements="0|1")
     */
    public function addFollowersAction(int $userId, string $followersLogin, int $count, int $async): Response
    {
        $user = $this->userService->findUserById($userId);
        if ($user !== null) {
            if ($async === 0) {
                $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
                $view = $this->view(['created' => $createdFollowers], 200);
            } else {
                $message = $this->subscriptionService->getFollowersMessages($user, $followersLogin, $count);
                $result = $this->asyncService->publishMultipleToExchange(AsyncService::ADD_FOLLOWER, $message);
                $view = $this->view(['success' => $result], $result ? 200 : 500);
            }
        } else {
            $view = $this->view(['success' => false], 404);
        }

        return $this->handleView($view);
    }
    ```
1. В файле `config/packages/old_sound_rabbit_mq.yaml` исправляем значение параметра `consumers.add_followers.qos_options`
    ```yaml
    qos_options: {prefetch_size: 0, prefetch_count: 30, global: false}
    ```
1. Выполняем запрос Add followers из Postman-коллекции v6 с параметром `async` = 1 и значением `count` = 100, видим 
   полученные сообщения в интерфейсе RabbitMQ
1. Запускаем два параллельных консьюмера командой
    ```shell
    php bin/console rabbitmq:consumer add_followers -m 100 &
    php bin/console rabbitmq:consumer add_followers -m 100 &
    ```
1. Видим в БД, что консьюмеры забирают по 30 сообщений и обрабатывают их параллельно, т.е. порядок обработки нарушен
1. Останавливаем консьюмеры командой `kill` c PID процессов консьюмеров

## 8. Эмулируем ошибку при работе с prefetch

1. В классе `App\Consumer\AddFollowersConsumer\Consumer` исправляем метод `execute`
    ```php
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

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->find($message->getUserId());
        if (!($user instanceof User)) {
            return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
        }

        if ($message->getFollowerLogin() === 'multi_follower_error #11') {
            throw new Exception('Planned error');
        }
        $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());
        sleep(1);

        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        return self::MSG_ACK;
    }
    ```
1. Выполняем запрос Add followers из Postman-коллекции v6 с параметрами `async` = 1, `followersLogin` =
   `multi_follower_error` и `count` = 100, видим полученные сообщения в интерфейсе RabbitMQ
1. Запускаем два параллельных консьюмера командой
    ```shell
    php bin/console rabbitmq:consumer add_followers -m 100 &
    php bin/console rabbitmq:consumer add_followers -m 100 &
    ```
1. Видим в БД, что после падения одного из консьюмеров порядок обработки вторым становится совсем нелогичным, и затем
   он тоже падает на том же сообщении, которое вернулось в очередь
1. Делаем Purge messages из очереди
1. В классе `App\Consumer\AddFollowersConsumer\Consumer` в методе `execute` изменяем проверяемый логин на
   `multi_follower_error2 #11_#0`
1. Выполняем запрос Add followers из Postman-коллекции v6 с параметрами `async` = 1, `followersLogin` =
   `multi_follower_error2` и `count` = 100, видим полученные сообщения в интерфейсе RabbitMQ
1. Запускаем консьюмер командой `php bin/console rabbitmq:consumer add_followers -m 100`
1. После падения перезапускаем консьюмер и видим, что он снова падает