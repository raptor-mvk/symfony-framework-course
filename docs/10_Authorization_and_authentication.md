1. Устанавливаем пакеты `symfony/security-bundle`, `symfony/maker-bundle` и `symfony/validator`
1. В файле `config/packages/security.yaml`
   1. Добавляем секцию `encoders`
       ```php
       encoders:
           App\Entity\User:
               algorithm: auto
       ```
   1. Исправляем секцию `providers`
       ```php
       providers:
           app_user_provider:
               entity:
                   class: App\Entity\User
                   property: login
       ```
   1. В секции `firewalls.main` заменяем `provider: users_in_memory` на `provider: app_user_provider`
1. Исправляем класс `App\Entity\User`
   1. добавляем `unique=true` к аннотации поля `$login`
   1. добавляем поля `$password` и `$roles`:
       ```php
       /**
        * @ORM\Column(type="string", length=120, nullable=false)
        */
       private string $password;
   
       /**
        * @ORM\Column(type="string", length=1024, nullable=false)
        */
       private string $roles;
       ```
   1. добавляем стандартные геттер и сеттер для поля `$password`
   1. добавляем геттер и сеттер для поля `$roles`
       ```php
       /**
        * @return string[]
        *
        * @throws JsonException
        */
       public function getRoles(): array
       {
           $roles = json_decode($this->roles, true, 512, JSON_THROW_ON_ERROR);
           // guarantee every user at least has ROLE_USER
           $roles[] = 'ROLE_USER';
   
           return array_unique($roles);
       }
   
       /**
        * @param string[] $roles
        *
        * @throws JsonException
        */
       public function setRoles(array $roles): void
       {
           $this->roles = json_encode($roles, JSON_THROW_ON_ERROR);
       }
       ```
   1. Имплементируем `Symfony\Component\Security\Core\User\UserInterface`, используя пустую реализацию методов
      `getSalt`, `eraseCredentials`, из метода `getUsername` возвращаем `$login`
   1. Исправляем метод `toArray`
       ```php
       /**
        * @throws JsonException
        */
       public function toArray(): array
       {
           return [
               'id' => $this->id,
               'login' => $this->login,
               'password' => $this->password,
               'roles' => $this->getRoles(),
               'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
               'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
               'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
               'followers' => array_map(static fn(User $user) => $user->getLogin(), $this->followers->toArray()),
               'authors' => array_map(static fn(User $user) => $user->getLogin(), $this->authors->toArray()),
           ];
       }
       ```
1. Заходим в контейнер командой `docker exec -it php sh`
   1. Генерируем миграцию командой `php bin/console doctrine:migrations:diff`
   1. Выполняем миграцию командой `php bin/console doctrine:migrations:migrate`
1. Добавляем класс `UserDTO`
    ```php
    <?php
    
    namespace App\DTO;
    
    use App\Entity\User;
    use JsonException;
    use Symfony\Component\Validator\Constraints as Assert;
    
    class UserDTO
    {
        /**
         * @Assert\NotBlank()
         * @Assert\Length(max=32)
         */
        public string $login;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Length(max=32)
         */
        public string $password;
    
        public array $roles;
    
        /**
         * @throws JsonException
         */
        public function __construct(array $data)
        {
            $this->login = $data['login'] ?? '';
            $this->password = $data['password'] ?? '';
            $this->roles = json_decode($data['roles'], true, 512, JSON_THROW_ON_ERROR) ?? [];
        }
    
        /**
         * @throws JsonException
         */
        public static function fromEntity(User $user): self
        {
            return new self([
                'login' => $user->getLogin(),
                'password' => $user->getPassword(),
                'roles' => $user->getRoles(),
            ]);
        }
    }
    ```
1. Класс `App\Service\UserSerivce`
    1. Добавляем DI `UserPasswordEncoderInterface` в конструктор
    1. Заменяем метод `saveUser`
        ```php
        /**
         * @throws JsonException
         */
        public function saveUser(User $user, UserDTO $userDTO): ?int
        {
            $user->setLogin($userDTO->login);
            $user->setPassword($this->userPasswordEncoder->encodePassword($user, $userDTO->password));
            $user->setRoles($userDTO->roles);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
    
            return $user->getId();
        }
        ```
   1. Заменяем метод `updateUser`
       ```
        /**
         * @throws JsonException
         */
        public function updateUser(int $userId, UserDTO $userDTO): bool
        {
            /** @var UserRepository $userRepository */
            $userRepository = $this->entityManager->getRepository(User::class);
            /** @var User $user */
            $user = $userRepository->find($userId);
            if ($user === null) {
                return false;
            }
    
            return $this->saveUser($user, $userDTO);
        }
       ```
1. Класс `App\Controller\Api\v1\UserController`
    1. Заменяем метод `saveUserAction`
        ```php
        /**
         * @Route("", methods={"POST"})
         *
         * @throws JsonException
         */
        public function saveUserAction(Request $request): Response
        {
            $userDTO = new UserDTO(
                [
                    'login' => $request->request->get('login'),
                    'password' => $request->request->get('password'),
                    'roles' => $request->request->get('roles'),
                ]
            );
            $userId = $this->userService->saveUser(new User(), $userDTO);
            [$data, $code] = $userId === null ?
                [['success' => false], 400] :
                [['success' => true, 'userId' => $userId], 200];
    
            return new JsonResponse($data, $code);
        }
        ```
    1. Заменяем метод `updateUserAction`
        ```php
        /**
         * @Route("", methods={"PATCH"})
         *
         * @throws JsonException
         */
        public function updateUserAction(Request $request): Response
        {
            $userId = $request->request->get('userId');
            $userDTO = new UserDTO(
                [
                    'login' => $request->request->get('login'),
                    'password' => $request->request->get('password'),
                    'roles' => $request->request->get('roles'),
                ]
            );
            $result = $this->userService->updateUser($userId, $userDTO);
    
            return new JsonResponse(['success' => $result], $result ? 200 : 404);
        }
        ```
1. Проверяем работоспособность API:
    1. Выполняем запрос Add user из Postman-коллекции v2
1. Исправляем файл `config/packages/security.yaml`
    1. добавляем `enable_authenticator_manager: true`
    1. в секции `firewall.main` заменяем `anonymous:true` на `security:false`
1. Генерируем форму логина `php bin/console make:auth`
    1. Выбираем `Login form authenticator`
    1. Указываем имя класса для аутентификатора и контроллера
    1. Не создаём `endpoint` для разлогинивания
1. Удаляем в файле `security/login.html.twig` зависимость от базового шаблона
1. Переходим по адресу `http://localhost:7777/login` и вводим логин/пароль пользователя, которого создали при проверке
   API. Видим, что после нажатия на `Sign in` ничего не происходит.
1. Убираем в файле `config/packages/security.yaml` в секции `firewall.main` строку `security:false`
1. Ещё раз переходим по адресу `http://localhost:7777/login` и вводим логин/пароль пользователя, после нажатия на
   `Sign in` получаем ошибку
1. Добавляем в классе аутентификатора в методе `onAuthenticationSuccess` редирект на `/api/v1/user`
1. Проверяем, что всё заработало
1. В файле `config/packages/security.yaml` в секции `access_control` добавляем условие
    ```yaml
    - { path: ^/api/v1/user, roles: ROLE_ADMIN, methods: [DELETE] }
    ```
1. Выполняем запрос Delete user из Postman-коллекции v2, добавив Cookie `PHPSESSID`, которую можно посмотреть в браузере
   после успешного логина. Проверяем, что возвращается ответ 403 с сообщением `Access denied`
1. Добавляем роль `ROLE_ADMIN` пользователю в БД, перелогинимся, чтобы получить корректную сессию и проверяем, что
   стал возвращаться ответ 200
1. В файле `config/packages/security.yaml` в секции `access_control` добавляем условие
    ```yaml
    - { path: ^/api/v1/user, roles: ROLE_VIEW, methods: [GET] }
    ```
1. Выполняем запрос Get user list из Postman-коллекции v2. Проверяем, что возвращается ответ 403 с сообщением
   `Access denied`
1. Добавляем в файл `config/packages/security.yaml` секцию `role_hierarchy`
    ```yaml
    role_hierarchy:
        ROLE_ADMIN: ROLE_VIEW
    ```
1. Ещё раз выполняем запрос Get user list из Postman-коллекции v2. Проверяем, что возвращается ответ 200
1. В класс `App\Service\UserService` добавляем метод `findUserById`
    ```php
    public function findUserById(int $userId): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->find($userId);
        return $user;
    }
    ```
1. Добавляем класс `App\Security\Voter\UserVoter`
    ```php
    <?php
    
    namespace App\Security\Voter;
    
    use App\Entity\User;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
    class UserVoter extends Voter
    {
        public const DELETE = 'delete';
    
        protected function supports(string $attribute, $subject): bool
        {
            return $attribute === self::DELETE && ($subject instanceof User);
        }
    
        protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
        {
            $user = $token->getUser();
            if (!$user instanceof User) {
                return false;
            }
    
            /** @var User $subject */
            return $user->getId() !== $subject->getId();
        }
    }
    ```
1. В классе `App\Controller\Api\v1\UserController`
    1. Добавляем в конструктор DI `AuthorizationCheckerInterface`
    1. Исправляем метод `deleteAction`
       ```php
       /**
        * @Route("", methods={"DELETE"})
        */
       public function deleteUserAction(Request $request): Response
       {
           $user = $this->userService->findUserById($id);
           if (!$this->authorizationChecker->isGranted(UserVoter::DELETE, $user)) {
               return new JsonResponse('Access denied', 403);
           }
           $result = $this->userService->deleteUserById($id);
       
           return new JsonResponse(['success' => $result], $result ? 200 : 404);
       }
       ```
1. Выполняем запрос Delete user из Postman-коллекции v2 cначала с идентификатором другого пользователя (не того,
   который залогинен), потом со своим идентификатором. Проверяем, что в первом случае ответ 200, во втором - 403
1. В файл `config/packages/security.yaml` добавляем секцию `access_decision_manager`
    ```yaml
    access_decision_manager:
        strategy: consensus
    ```
1. Добавляем класс `App\Security\Voter\FakeUserVoter`
    ```php
    <?php
    
    namespace App\Security\Voter;
    
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
    class FakeUserVoter extends Voter
    {
    
        protected function supports(string $attribute, $subject): bool
        {
            return true;
        }
    
        protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
        {
            return false;
        }
    }
    ```
1. Добавляем класс `App\Security\Voter\DummyUserVoter`
    ```php
    <?php
    
    namespace App\Security\Voter;
        
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
    class DummyUserVoter extends Voter
    {
    
        protected function supports(string $attribute, $subject): bool
        {
            return true;
        }
    
        protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
        {
            return false;
        }
    }
    ```
1. Проверяем, что можно удалить другого пользователя тоже больше нельзя
