1. Запускаем контейнеры командой `docker-compose up`
1. Заходим в контейнер `php` командой `docker exec -it php sh`, далее все команды выполняются в контейнере
1. Очищаем БД, удаляя все таблицы
1. Удаляем файл `migrations/Version20210212155210.php`
1. Генерируем новый файл миграции командой `php bin/console doctrine:migrations:diff`   
1. Открываем сгенерированный файл и видим в методе `down` команду `$this->addSql('CREATE SCHEMA public');`
1. Создаём класс `App\Symfony\MigrationEventSubscriber`
    ```php
    <?php
    
    namespace App\Symfony;
    
    use Doctrine\Common\EventSubscriber;
    use Doctrine\DBAL\Schema\SchemaException;
    use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
    
    class MigrationEventSubscriber implements EventSubscriber
    {
        /**
         * @return string[]
         */
        public function getSubscribedEvents(): array
        {
            return ['postGenerateSchema'];
        }
    
        /**
         * @param GenerateSchemaEventArgs $args
         *
         * @throws SchemaException
         */
        public function postGenerateSchema(GenerateSchemaEventArgs $args): void
        {
            $schema = $args->getSchema();
            if (!$schema->hasNamespace('public')) {
                $schema->createNamespace('public');
            }
        }
    }
    ```
1. В файле `config/service.yaml` добавляем описание нового сервиса
    ```yaml
    App\Symfony\MigrationEventSubscriber:
        tags:
            - { name: doctrine.event_subscriber, connection: default }
    ```
1. Удаляем неправильно сгенерированный файл и перегенерируем его командой
    `php bin/console doctrine:migrations:diff`
1. Видим, что в сгенерированном файле ненужная команда не появилась
1. Обращаем внимание, что имена индексов в миграции сгенерированы автоматически
1. Исправляем аннотацию класса `App\Entity\Tweet`
    ```php
    /**
     * @ORM\Table(
     *     name="tweet",
     *     indexes={
     *         @ORM\Index(name="tweet__author_id__ind", columns={"author_id"})
     *     }
     * )
     * @ORM\Entity
     */
    ```
1. Исправляем аннотацию класса `App\Entity\Subscription`
    ```php
    /**
     * @ORM\Table(
     *     name="subscription",
     *     indexes={
     *         @ORM\Index(name="subscription__author_id__ind", columns={"author_id"}),
     *         @ORM\Index(name="subscription__follower_id__ind", columns={"follower_id"})
     *     }
     * )
     * @ORM\Entity
     */
    ```
1. Удаляем неправильно сгенерированный файл и перегенерируем его командой
    `php bin/console doctrine:migrations:diff`
1. Исправляем в сгенерированной миграции вручную оставшиеся автоматически сгенерированными имена индексов и имена
    внешних ключей
    ```php
    <?php
   
    declare(strict_types=1);
   
    namespace DoctrineMigrations;
   
    use Doctrine\DBAL\Schema\Schema;
    use Doctrine\Migrations\AbstractMigration;
   
    /**
     * Auto-generated Migration: Please modify to your needs!
     */
    final class Version20210215164758 extends AbstractMigration
    {
        public function getDescription() : string
        {
           return '';
        }
   
        public function up(Schema $schema) : void
        {
           // this up() migration is auto-generated, please modify it to your needs
           $this->addSql('CREATE TABLE subscription (id BIGSERIAL NOT NULL, author_id BIGINT DEFAULT NULL, follower_id BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
           $this->addSql('CREATE INDEX subscription__author_id__ind ON subscription (author_id)');
           $this->addSql('CREATE INDEX subscription__follower_id__ind ON subscription (follower_id)');
           $this->addSql('CREATE TABLE tweet (id BIGSERIAL NOT NULL, author_id BIGINT DEFAULT NULL, text VARCHAR(140) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
           $this->addSql('CREATE INDEX tweet__author_id__ind ON tweet (author_id)');
           $this->addSql('CREATE TABLE "user" (id BIGSERIAL NOT NULL, login VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
           $this->addSql('CREATE TABLE author_follower (author_id BIGINT NOT NULL, follower_id BIGINT NOT NULL, PRIMARY KEY(author_id, follower_id))');
           $this->addSql('CREATE INDEX author_follower__author_id__ind ON author_follower (author_id)');
           $this->addSql('CREATE INDEX author_follower__follower_id__ind ON author_follower (follower_id)');
           $this->addSql('ALTER TABLE subscription ADD CONSTRAINT subscription__author_id__fk FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
           $this->addSql('ALTER TABLE subscription ADD CONSTRAINT subscription__follower_id__fk FOREIGN KEY (follower_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
           $this->addSql('ALTER TABLE tweet ADD CONSTRAINT tweet__author_id__fk FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
           $this->addSql('ALTER TABLE author_follower ADD CONSTRAINT author_follower__author_id__fk FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
           $this->addSql('ALTER TABLE author_follower ADD CONSTRAINT author_follower__follower_id__fk FOREIGN KEY (follower_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        }
   
        public function down(Schema $schema) : void
        {
           // this down() migration is auto-generated, please modify it to your needs
           $this->addSql('ALTER TABLE subscription DROP CONSTRAINT author_follower__follower_id__fk');
           $this->addSql('ALTER TABLE subscription DROP CONSTRAINT author_follower__author_id__fk');
           $this->addSql('ALTER TABLE tweet DROP CONSTRAINT tweet__author_id__fk');
           $this->addSql('ALTER TABLE author_follower DROP CONSTRAINT subscription__follower_id__fk');
           $this->addSql('ALTER TABLE author_follower DROP CONSTRAINT subscription__author_id__fk');
           $this->addSql('DROP TABLE subscription');
           $this->addSql('DROP TABLE tweet');
           $this->addSql('DROP TABLE "user"');
           $this->addSql('DROP TABLE author_follower');
        }
    }
    ```
1. Выполняем миграцию командой `php bin/console doctrine:migrations:migrate`
1. Ещё раз генерируем миграцию, выравнивающую схему БД с описаниями Entity командой
    `php bin/console doctrine:migrations:diff`
1. Заходим в сгенерированный файл и видим, что имена индексов для отношения many-to-many переопределить не удаётся
1. Накатываем миграцию командой `php bin/console doctrine:migrations:migrate`
1. Проверяем в БД, что имена индексов изменились
1. Откатываем миграцию командой `php bin/console doctrine:migrations:migrate VERSION`, где VERSION - FQN класса с
    первой миграцией, создающей все таблицы
1. Проверяем в БД, что имена индексов снова стали осмысленными
1. Снова накатываем последнюю миграцию командой `php bin/console doctrine:migrations:migrate`
1. Ещё раз генерируем выравнивающую миграцию командой `php bin/console doctrine:migrations:diff`, видим ошибку,
    говорящую о том, что расхождений больше нет
1. Создаём интерфейс `App\Entity\HasMetaTimestampsInterface`
    ```php
    <?php
   
    namespace App\Entity;
   
    interface HasMetaTimestampsInterface
    {
        public function setCreatedAt(): void;
   
        public function setUpdatedAt(): void;
    }
    ```
1. Имплементируем созданный интерфейс в классе `App\Entity\User` (нужный метод уже есть)
1. Создаём класс `App\Symfony\MetaTimestampsPrePersistEventListener`
    ```php
    <?php
   
    namespace App\Symfony;
   
    use App\Entity\HasMetaTimestampsInterface;
    use Doctrine\Persistence\Event\LifecycleEventArgs;
   
    class MetaTimestampsPrePersistEventListener
    {
        public function prePersist(LifecycleEventArgs $event): void
        {
            $entity = $event->getObject();
    
            if ($entity instanceof HasMetaTimestampsInterface) {
                $entity->setCreatedAt();
                $entity->setUpdatedAt();
            }
        }
    }
    ```
1. В файле `config/services.yaml` добавляем новый сервис
    ```yaml
    App\Symfony\MetaTimestampsPrePersistEventListener:
        tags:
            - { name: doctrine.event_listener, event: prePersist }
    ```
1. В классе `App\Service\UserService` исправляем метод `create`
    ```php
    public function create(string $login): User
    {
        $user = new User();
        $user->setLogin($login);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
   
        return $user;
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $user = $this->userService->create('J.R.R. Tolkien');
   
        return $this->json($user->toArray());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим данные нашего пользователя с проставленным временем
   создания и редактирования
1. Убираем наш listener из файла `config/services.yaml`
1. В классе `App\Entity\User`
    1. Исправляем аннотацию класса
        ```php
        /**
         * @ORM\Table(name="`user`")
         * @ORM\Entity
         * @ORM\HasLifecycleCallbacks()
         */
        ```
    1. Исправляем методы `setCreatedAt` и `setUpdatedAt`, добавляя к каждому аннотацию
        ```php
        /**
         * @ORM\PrePersist()
         */
        ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что время в пользователе снова проставилось
1. В классе `App\Entity\User` исправляем аннотацию для метода `setUpdatedAt`
    ```php
    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    ```
1. В классе `App\Service\UserService` добавляем новый метод `updateUserLogin`
    ```php
    public function updateUserLogin(User $user, string $login): void
    {
        $user->setLogin($login);
        $this->entityManager->flush();
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello` (в методе `findUser` используем любой ID, который
   реально существует в БД)
    ```php
    public function hello(): Response
    {
        $user = $this->userService->findUser(3);
        if ($user === null) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }
        $this->userService->updateUserLogin($user, 'My new user');
    
        return $this->json($user->toArray());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что поле `updatedAt` обновилось
1. Устанавливаем пакет doctrine-extensions-bundle командой `composer require stof/doctrine-extensions-bundle`
1. В файле `config/packages/stof_doctrine_extensions.yaml` добавляем конфигурацию для ORM
    ```yaml
    orm:
        default:
            timestampable: true
    ```
1. В классе `App\Entity\User`
    1. Исправляем аннотацию перед классом
        ```php
        /**
         * @ORM\Table(name="`user`")
         * @ORM\Entity
         */
        ```
    1. Убираем аннотации у методов `setCreatedAt`, `setUpdatedAt`
    1. Добавляем аннотации для полей `createdAt` и `updatedAt`
        ```php
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
        ```
1. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $user = $this->userService->create('Terry Pratchett');
        sleep(1);
        $this->userService->updateUserLogin($user, 'Lewis Carroll');
    
        return $this->json($user->toArray());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что поля с временем заполнились
1. В класс `App\Service\UserService` добавляем новый метод `findUsersWithQueryBuilder`
    ```php
    public function findUsersWithQueryBuilder(string $login): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('u')
            ->from(User::class, 'u')
            ->andWhere($queryBuilder->expr()->like('u.login',':userLogin'))
            ->setParameter('userLogin', "%$login%");
    
        return $queryBuilder->getQuery()->getResult();
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $users = $this->userService->findUsersWithQueryBuilder('Lewis');
    
        return $this->json(array_map(static fn(User $user) => $user->toArray(), $users));
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим найденную запись
1. В класс `App\Service\UserService` добавляем новый метод `updateUserLoginWithQueryBuilder`
    ```php
    public function updateUserLoginWithQueryBuilder(int $userId, string $login): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->update(User::class,'u')
            ->set('u.login', ':userLogin')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId)
            ->setParameter('userLogin', $login);

        $queryBuilder->getQuery()->execute();
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello` (в методе `findUser` используем любой ID, который
   реально существует в БД)
    ```php
    public function hello(): Response
    {
        $user = $this->userService->findUser(3);
        if ($user === null) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }
        $this->userService->updateUserLoginWithQueryBuilder($user->getId(), 'User is updated');
    
        return $this->json($user->toArray());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим запись со старым логином и старым временем обновления
1. Проверяем, что в БД запись обновилась
1. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $userId = 5;
        $user = $this->userService->findUser($userId);
        if ($user === null) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }
        $this->userService->updateUserLoginWithQueryBuilder($user->getId(), 'User is updated twice');
        $this->userService->clearEntityManager();
        $user = $this->userService->findUser($userId);
    
        return $this->json($user->toArray());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим запись с новым логином, но старым временем обновления
1. В класс `App\Service\UserService` добавляем новый метод `updateUserLoginWithDBALQueryBuilder`
    ```php
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateUserLoginWithDBALQueryBuilder(int $userId, string $login): void
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder();
        $queryBuilder->update('"user"','u')
            ->set('login', ':userLogin')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId)
            ->setParameter('userLogin', $login);

        $queryBuilder->execute();
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $userId = 5;
        $user = $this->userService->findUser($userId);
        if ($user === null) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }
        $this->userService->updateUserLoginWithDBALQueryBuilder($user->getId(), 'User is updated by DBAL');
        $this->userService->clearEntityManager();
        $user = $this->userService->findUser($userId);

        return $this->json($user->toArray());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим запись с новым логином
1. В класс `App\Service\UserService` добавляем новый метод `findUserWithTweetsWithQueryBuilder`
    ```php
    public function findUserWithTweetsWithQueryBuilder(int $userId): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('u')
            ->from(User::class, 'u')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId);
    
        return $queryBuilder->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello`
    ```php
    public function hello(): Response
    {
        $author = $this->userService->create('Charles Dickens');
        $this->userService->postTweet($author, 'Oliver Twist');
        $this->userService->postTweet($author, 'The Christmas Carol');
        $userData = $this->userService->findUserWithTweetsWithQueryBuilder($author->getId());

        return $this->json($userData);
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что твиты не подгружаются
1. В классе `App\Service\UserService` исправляем метод `findUserWithTweetsWithQueryBuilder`
    ```php
    public function findUserWithTweetsWithQueryBuilder(int $userId): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('u', 't')
            ->from(User::class, 'u')
            ->leftJoin('u.tweets', 't')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId);
    
        return $queryBuilder->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что твиты подгружаются
1. В класс `App\Service\UserService` добавляем новый метод `findUserWithTweetsWithDBALQueryBuilder`
    ```php
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findUserWithTweetsWithDBALQueryBuilder(int $userId): array
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder();
        $queryBuilder->select('u', 't')
            ->from('"user"', 'u')
            ->leftJoin('u', 'tweet', 't', 'u.id = t.author_id')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId);
    
        return $queryBuilder->execute()->fetchAllNumeric();
    }
    ```
1. В классе `App\Controller\WorldController` исправляем метод `hello`, заменяя вызов метода 
   `findUserWithTweetsWithQueryBuilder` на вызов метода `findUserWithTweetsWithDBALQueryBuilder`