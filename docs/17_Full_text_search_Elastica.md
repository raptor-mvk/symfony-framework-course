# 1. Elasticsearch и Kibana для логов

1. Добавляем сервисы `elasticsearch` и `kibana` в `docker-compose.yml`
    ```yaml
    elasticsearch:
        image: docker.elastic.co/elasticsearch/elasticsearch:7.9.2
        container_name: 'elasticsearch'
        environment:
          - cluster.name=docker-cluster
          - bootstrap.memory_lock=true
          - discovery.type=single-node
          - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
        ulimits:
          memlock:
            soft: -1
            hard: -1
        ports:
          - 9200:9200
          - 9300:9300
    kibana:
        image: docker.elastic.co/kibana/kibana:7.9.2
        container_name: 'kibana'
        depends_on:
          - elasticsearch
        ports:
          - 5601:5601
    ```
1. Запускаем контейнеры командой `docker-compose up -d`
1. Устанавливаем пакет `symfony/http-client`
1. В файле `config/packages/dev/monolog.yaml`
    1. Добавляем поле `channels: [elasticsearch]`
    1. Добавляем новый обработчик в секцию `handlers`
        ```yaml
        elasticsearch:
            type: service
            id: Symfony\Bridge\Monolog\Handler\ElasticsearchLogstashHandler
            channels: elasticsearch
        ```
    1. Добавляем секцию `services`:
        ```yaml
        services:
            Psr\Log\NullLogger:
                class: Psr\Log\NullLogger
        
            http_client_without_logs:
                class: Symfony\Component\HttpClient\CurlHttpClient
                calls:
                    - [setLogger, ['@Psr\Log\NullLogger']]
        
            Symfony\Bridge\Monolog\Handler\ElasticsearchLogstashHandler:
                arguments:
                    - 'http://elasticsearch:9200'
                    - 'monolog'
                    - '@http_client_without_logs'
        ```
1. В классе `App\Controller\Api\SaveUser\v4\SaveUserManager`
    1. Добавляем в конструктор параметр `LoggerInterface $elasticsearchLogger`
    1. Исправляем метод `saveUser`
        ```php
        public function saveUser(SaveUserDTO $saveUserDTO): UserIsSavedDTO
        {
            $user = new User();
            $user->setLogin($saveUserDTO->login);
            $user->setPassword($saveUserDTO->password);
            $user->setRoles($saveUserDTO->roles);
            $user->setAge($saveUserDTO->age);
            $user->setIsActive($saveUserDTO->isActive);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->logger->info("User #{$user->getId()} is saved: [{$user->getLogin()}, {$user->getAge()} yrs]");
    
            $result = new UserIsSavedDTO();
            $context = (new SerializationContext())->setGroups(['user1', 'user2']);
            $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));
    
            return $result;
        }
        ```
1. Выполняем запрос Add user v4 из Postman-коллекции v8, видим логи в файле`var/log/dev.log`
1. Заходим в Kibana `http://localhost:5061`
1. Создаём index pattern на базе индекса `monolog`, переходим в `Discover`, видим наше сообщение

# 2. Индексация данных БД в Elasticsearch

## 1. Установка elastica-bundle

1. Устанавливаем пакет `friendsofsymfony/elastica-bundle:6.0.x-dev`
1. В файле `.env` исправляем DSN для ElasticSearch
    ```shell script
    ELASTICSEARCH_URL=http://elasticsearch:9200/
    ```
1. Выполняем запрос Add followers из Postman-коллекции v8, чтобы получить побольше записей в БД
1. В файле `config/packages/fos_elastica.yaml` в секции `indexes` удаляем `app` и добавляем секцию `user`:
    ```yaml
    user:
        persistence:
            driver: orm
            model: App\Entity\User
        properties:
            login: ~
            age: ~
            phone: ~
            email: ~
            preferred: ~
    ```
1. В классе `App\Entity\User` исправляем типы для полей `phone`, `email`, `preferred`
    ```php
    /**
     * @ORM\Column(type="string", length=11, nullable=true)
     */
    private ?string $phone;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    private ?string $email;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private ?string $preferred;
    ```
1. Заходим в контейнер с php командой `docker exec -it php sh` и заполняем индекс командой
`php bin/console fos:elastica:populate`
1. В Kibana заходим в Stack Management -> Index patterns и создаём index pattern на базе индекса `user`
1. Переходим в `Discover`, видим наши данные в новом шаблоне

## 2. Вложенные документы

1. Выполняем запрос Post tweet из Postman-коллекции v8, чтобы получить запись в таблице `tweet`
1. Добавим индекс с составными полями в `config/packages/fos_elastica.yaml` в секцию `indexes`
    ```yaml
    tweet:
        persistence:
            driver: orm
            model: App\Entity\Tweet
            provider: ~
            finder: ~
        properties:
            author:
                type: nested
                properties:
                    name:
                        property_path: login
                    age: ~
                    phone: ~
                    email: ~
                    preferred: ~
            text: ~
    ```
1. В контейнере ещё раз заполняем индекс командой `php bin/console fos:elastica:populate`
1. В Kibana заходим в Stack Management -> Index patterns и создаём index pattern на базе индекса `tweet`
1. Переходим в `Discover`, видим наши данные в новом шаблоне

## 3. Сериализация вместо описания схемы

1. В файле `config/packages/fos_elastica.yaml`
    1. Включаем сериализацию
        ```yaml
        serializer:
            serializer: jms_serializer
        ```
    1. Для каждого индекса (`user`, `tweet`) удаляем секцию `properties` и добавляем секцию `serializer`
        ```yaml
        serializer:
            groups: [elastica]
        ```
1. В классе `App\Entity\User` добавляем аннотации на `login`, `phone`, `email` и `preferred`
    ```php
    /**
     * @ORM\Column(type="string", length=32, nullable=false, unique=true)
     * @JMS\Groups({"user1","elastica"})
     */
    private string $login;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @JMS\Type("int")
     * @JMS\Groups({"user1","elastica"})
     */
    private int $age;

    /**
     * @ORM\Column(type="string", length=11, nullable=true)
     * @JMS\Type("string")
     * @JMS\Groups({"elastica"})
     */
    private ?string $phone = null;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     * @JMS\Type("string")
     * @JMS\Groups({"elastica"})
     */
    private ?string $email = null;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     * @JMS\Type("string")
     * @JMS\Groups({"elastica"})
     */
    private ?string $preferred = null;
    ```
1. В классе `App\Entity\Tweet` добавляем аннотации на `author` и `text`
    ```php
    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="tweets")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="author_id", referencedColumnName="id")
     * })
     * @JMS\Groups({"elastica"})
     */
    private User $author;

    /**
     * @ORM\Column(type="string", length=140, nullable=false)
     * @JMS\Type("string")
     * @JMS\Groups({"elastica"})
     */
    private string $text;
    ```
1. В контейнере ещё раз заполняем индекс командой `php bin/console fos:elastica:populate`, получаем ошибку
1. В файле `config\packages\jms_serializer.yml` отключаем опцию `JSON_PRETTY_PRINT`
1. В контейнере ещё раз заполняем индекс командой `php bin/console fos:elastica:populate`, на этот раз ошибки нет
1. Проверяем в Kibana, что в индексах данные присутствуют

## 4. Отключаем автообновление индекса

1. Выполняем запрос Add user v4 из Postman-коллекции v8
1. Проверяем в Kibana, что новая запись появилась в индексе
1. Отключаем listener для insert в файле `config/fos_elastica.yaml` в секции `indexes.user.persistence` добавляем секцию
`listener`
    ```yaml
    listener:
        insert: false
        update: true
        delete: true
    ```
1. Выполняем ещё один запрос Add user v4 из Postman-коллекции v8
1. Проверяем в Kibana, что новая запись не появилась в индексе, хотя в БД она есть

## 5. Поиск по индексу

1. В классе `App\Service\UserService`
    1. Добавляем зависимость от `PaginatedFinderInterface`
    1. Добавляем метод `findUserByQuery`
        ```php
        /**
         * @return User[]
         */
        public function findUserByQuery(string $query, int $perPage, int $page): array
        {
            $paginatedResult = $this->finder->findPaginated($query);
            $paginatedResult->setMaxPerPage($perPage);
            $paginatedResult->setCurrentPage($page);
            $result = [];
            array_push($result, ...$paginatedResult->getCurrentPageResults());
    
            return $result;
        }
        ```
1. В файле `config/services.yaml` добавляем новый сервис:
    ```yaml
    App\Service\UserService:
        arguments:
            $finder: '@fos_elastica.finder.user'
    ```
1. Добавляем класс `App\Controller\Api\GetUsersByQuery\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\GetUsersByQuery\v1;
    
    use App\Service\UserService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\QueryParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait;
    
        private UserService $userService;
    
        public function __construct(UserService $userService, ViewHandlerInterface $viewHandler)
        {
            $this->userService = $userService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Get("/api/v1/get-users-by-query")
         *
         * @QueryParam(name="query")
         * @QueryParam(name="perPage", requirements="\d+")
         * @QueryParam(name="page", requirements="\d+")
         */
        public function getUsersByQueryAction(string $query, int $perPage, int $page): Response
        {
            return $this->handleView($this->view($this->userService->findUserByQuery($query, $perPage, $page), 200));
        }
    }
    ```
1. Выполняем несколько запросов Get users by query из Postman-коллекции v8 с данными из разных полей разных
   пользователей 

## 6. Добавляем агрегацию

1. В классе `App\Service\UserService` добавляем метод findUserWithAggregation
    ```php
    /**
     * @return User[]
     */
    public function findUserWithAggregation(string $field): array
    {
        $aggregation = new Terms('notifications');
        $aggregation->setField($field);
        $query = new Query();
        $query->addAggregation($aggregation);
        $paginatedResult = $this->finder->findPaginated($query);
        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $paginatedResult->getAdapter();

        return $adapter->getAggregations();
    }
    ```
1. Добавляем класс `App\Controller\Api\GetUsersWithAggregation\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\GetUsersWithAggregation\v1;
    
    use App\Service\UserService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\QueryParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait;
    
        private UserService $userService;
    
        public function __construct(UserService $userService, ViewHandlerInterface $viewHandler)
        {
            $this->userService = $userService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Get("/api/v1/get-users-with-aggregation")
         *
         * @QueryParam(name="field")
         */
        public function getUsersWithAggregationAction(string $field): Response
        {
            return $this->handleView($this->view($this->userService->findUserWithAggregation($field), 200));
        }
    }
    ```
1. Выполняем запрос Get users with aggregation из Postman-коллекции v8, получаем ошибку
1. Добавляем в `config/packages/fos_elastica.yaml` в секцию `indexes.user` секцию `properties`
    ```yaml
    properties:
        preferred:
            fielddata: true
    ```
1. В контейнере заполняем индекс командой `php bin/console fos:elastica:populate`
1. Ещё раз выполняем запрос Get users with aggregation из Postman-коллекции v8, получаем агрегацию по полю `preferred`

## 7. Совмещаем агрегацию и поиск

1. В классе `App\Service\UserService` добавляем метод `findUserByQueryWithAggregation`
    ```php
    /**
     * @return User[]
     */
    public function findUserByQueryWithAggregation(string $queryString, string $field): array
    {
        $aggregation = new Terms('notifications');
        $aggregation->setField($field);
        $query = new Query(new QueryString($queryString));
        $query->addAggregation($aggregation);
        $paginatedResult = $this->finder->findPaginated($query);
        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $paginatedResult->getAdapter();
    
        return $adapter->getAggregations();
    }
    ```
1. Добавляем класс `App\Controller\Api\GetUsersByQueryWithAggregation\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\GetUsersByQueryWithAggregation\v1;
    
    use App\Service\UserService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\QueryParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait;
    
        private UserService $userService;
    
        public function __construct(UserService $userService, ViewHandlerInterface $viewHandler)
        {
            $this->userService = $userService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Get("/api/v1/get-users-by-query-with-aggregation")
         *
         * @QueryParam(name="query")
         * @QueryParam(name="field")
         */
        public function getUsersByQueryWithAggregationAction(string $query, string $field): Response
        {
            return $this->handleView($this->view($this->userService->findUserByQueryWithAggregation($query, $field), 200));
        }
    }
    ```
1. Выполняем запрос Get users by query with aggregation из Postman-коллекции v8, получаем агрегацию найденных
   пользователей по полю `preferred`
