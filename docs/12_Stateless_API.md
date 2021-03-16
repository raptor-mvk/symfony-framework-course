1. Запускаем контейнеры командой `docker-compose up -d`
1. Устанавливаем пакет `lexik/jwt-authentication-bundle`
1. В файле `config/packages/security.yaml` добавляем в секцию `firewalls`
    ```yaml
    token:
        pattern: ^/api/v1/token
        security: false
    ``` 
1. В класс `App\Entity\User` добавляем поле `$token` и стандартные геттер/сеттер для него
    ```php
    /**
     * @ORM\Column(type="string", length=32, nullable=true, unique=true)
     */
    private string $token;
    ```
1. Входим в контейнер командой `docker exec -it php sh`
1. Готовим миграцию командой `php bin/console doctrine:migrations:diff`
1. Готовим миграцию командой `php bin/console doctrine:migrations:migrate`
1. В файл `App\Service\UserService` добавляем
    ```php
    public function findUserByLogin(string $login): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->findOneBy(['login' => $login]);
        
        return $user;
    }

    public function updateUserToken(string $login): ?string
    {
	$user = $this->findUserByLogin($login);
        if ($user === null) {
            return false;
        }
        $token = base64_encode(random_bytes(20));
        $user->setToken($token);
        $this->entityManager->flush();
        
        return $token;
    }
    ```
1. Добавляем класс `App\Service\AuthService`
    ```php
    <?php
    
    namespace App\Service;
    
    use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
    
    class AuthService
    {
        private UserService $userService;
        private UserPasswordEncoderInterface $passwordEncoder;
    
        public function __construct(UserService $userService, UserPasswordEncoderInterface $passwordEncoder)
        {
            $this->userService = $userService;
            $this->passwordEncoder = $passwordEncoder;
        }
    
        public function isCredentialsValid(string $login, string $password): bool
        {
            $user = $this->userService->findUserByLogin($login);
            if ($user === null) {
                return false;
            }
    
            return $this->passwordEncoder->isPasswordValid($user, $password);
        }
    
        public function getToken(string $login): ?string
        {
            return $this->userService->updateUserToken($login);
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\v1\TokenController`
    ```php
    <?php
    
    namespace App\Controller\Api\v1;
    
    use App\Service\AuthService;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Annotation\Route;
    
    /**
     * @Route("/api/v1/token")
     */
    class TokenController
    {
        private AuthService $authService;
    
        public function __construct(AuthService $authService)
        {
            $this->authService = $authService;
        }
    
        /**
         * @Route("", methods={"POST"})
         */
        public function getTokenAction(Request $request): Response
        {
            $user = $request->getUser();
            $password = $request->getPassword();
            if (!$user || !$password) {
                return new JsonResponse(['message' => 'Authorization required'], Response::HTTP_UNAUTHORIZED);
            }
            if (!$this->authService->isCredentialsValid($user, $password)) {
                return new JsonResponse(['message' => 'Invalid password or username'], Response::HTTP_FORBIDDEN);
            }
    
            return new JsonResponse(['token' => $this->authService->getToken($user)]);
        }
    }
    ```
1. Выполняем запрос Get token из Postman-коллекции v4 без авторизации, получаем ошибку 401
1. Выполняем запрос Get token из Postman-коллекции v4 с неверными реквизитами, получаем ошибку 403
1. Выполняем запрос Get token из Postman-коллекции v4 с верными реквизитами, получаем токен
1. Удаляем классы `App\Security\Voter\DummyUserVoter` и `App\Security\Voter\FakeUserVoter`
1. В файле `config/packages/security.yaml` меняем содержимое секции `firewalls.main`
    ```yaml
    stateless: true
    guard:
        authenticators:
            - App\Security\ApiTokenAuthenticator
    ```
1. В файл `App\Service\UserService` добавляем
    ```php
    public function findUserByToken(string $token): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->findOneBy(['token' => $token]);

        return $user;
    }
    ```
1. Добавляем класс `App\Security\ApiTokenAuthenticator`
    ```php
    <?php
    
    namespace App\Security;
    
    use App\Service\UserService;
    use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Exception\AuthenticationException;
    use Symfony\Component\Security\Core\User\UserInterface;
    use Symfony\Component\Security\Core\User\UserProviderInterface;
    use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
    
    class ApiTokenAuthenticator extends AbstractGuardAuthenticator
    {
        /** @var UserService */
        private $userService;
    
        public function __construct(UserService $userService)
        {
            $this->userService = $userService;
        }
    
        public function start(Request $request, AuthenticationException $authException = null)
        {
            return new JsonResponse(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
    
        public function supports(Request $request)
        {
            return true;
        }
    
        public function getCredentials(Request $request)
        {
            $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');

            return $extractor->extract($request);
        }
    
        public function getUser($credentials, UserProviderInterface $userProvider)
        {
            return $this->userService->findUserByToken($credentials);
        }
    
        public function checkCredentials($credentials, UserInterface $user)
        {
            return $user !== null;
        }
    
        public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
        {
            return new JsonResponse(['message' => 'Invalid API Token'], Response::HTTP_FORBIDDEN);
        }
    
        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey)
        {
        }
    
        public function supportsRememberMe()
        {
            return false;
        }
    }
    ```
1. Выполняем запрос Get user list из Postman-коллекции v4 без авторизации, получаем ошибку 403
1. Выполняем запрос Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию запроса Get user
   list и выполняем его, видим, что ответ возвращается
1. Удаляем у пользователя в БД роль `ROLE_ADMIN` и проверяем, что запрос Get user list сразу же возвращает ошибку 500 с
   текстом `Access Denied`
1. Создаём каталог `config/jwt`
1. Генерируем ключи, используя passphrase из файла `.env` командами
    ```shell
    openssl genrsa -out config/jwt/private.pem -aes256 4096
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
    ```
1. В файл `.env` добавляем параметр
    ```shell
    JWT_TTL_SEC=3600
    ```
1. В файл `config/packages/lexik_jwt_authentication.yaml` добавляем строку
    ```yaml
    token_ttl: '%env(JWT_TTL_SEC)%'
    ```
1. В классе `App\Service\AuthService`
    1. Добавляем в конструктор имплементацию `JWTEncoderInterface` и целочисленный параметр `tokenTTL`
    1. Исправляем метод `getToken`
    ```
    public function getToken(string $login): string
    {
        $tokenData = ['username' => $login, 'exp' => time() + $this->tokenTTL];

        return $this->jwtEncoder->encode($tokenData);
    }
    ```
1. В файле `config/services.yaml` добавляем новый сервис
    ```yaml
    App\Service\AuthService:
        arguments:
            $tokenTTL: '%env(JWT_TTL_SEC)%'
    ```
1. Добавляем класс `App\Security\AuthUser`
    ```php
    <?php
    
    namespace App\Security;
    
    use Symfony\Component\Security\Core\User\UserInterface;
    
    class AuthUser implements UserInterface
    {
        private string $username;
        private array $roles;
    
        public function __construct(array $credentials)
        {
            $this->username = $credentials['username'];
            $this->roles = array_unique(array_merge($credentials['roles'] ?? [], ['ROLE_USER']));
        }
    
        public function getRoles()
        {
            return $this->roles;
        }
    
        public function getPassword()
        {
            return '';
        }
    
        public function getSalt()
        {
            return '';
        }
    
        public function getUsername()
        {
            return $this->username;
        }
    
        public function eraseCredentials()
        {
        }
    }
    ```
1. Добавляем класс `App\Security\JwtTokenAuthenticator`
    ```php
    <?php
    
    namespace App\Security;
    
    use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
    use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
    use Symfony\Component\Security\Core\Exception\AuthenticationException;
    use Symfony\Component\Security\Core\User\UserInterface;
    use Symfony\Component\Security\Core\User\UserProviderInterface;
    use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
    
    class JwtTokenAuthenticator extends AbstractGuardAuthenticator
    {
        private JWTEncoderInterface $jwtEncoder;
    
        public function __construct(JWTEncoderInterface $jwtEncoder)
        {
            $this->jwtEncoder = $jwtEncoder;
        }
    
        public function start(Request $request, AuthenticationException $authException = null)
        {
            return new JsonResponse(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
    
        public function supports(Request $request)
        {
            return true;
        }
    
        public function getCredentials(Request $request)
        {
            $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
            $token = $extractor->extract($request);
            return $this->jwtEncoder->decode($token);
        }
    
        public function getUser($credentials, UserProviderInterface $userProvider)
        {
            return empty($credentials['username']) ? null : new AuthUser($credentials);
        }
    
        public function checkCredentials($credentials, UserInterface $user)
        {
            return !empty($credentials['username']);
        }
    
        public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
        {
            return new JsonResponse(['message' => 'Invalid JWT Token'], Response::HTTP_FORBIDDEN);
        }
    
        public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey)
        {
            return null;
        }
    
        public function supportsRememberMe()
        {
            return false;
        }
    }
    ```
1. В файле `config/packages/security.yaml` заменяем в секции `firewalls.main.guard.authenticators` аутентификатор на
   `App\Security\JwtTokenAuthenticator`
1. Возвращаем пользователю в БД роль `ROLE_ADMIN`
1. Выполняем запрос Get user list из Postman-коллекции v4 со старым токеном, получаем ошибку 500 с сообщением `Invalid
   JWT Token`
1. Выполняем запрос Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию запроса Get user
   list и выполняем его, получаем ошибку 500 с сообщением `Access Denied`
1. Заменяем в `App\Service\AuthService` метод `getToken`
    ```php
    public function getToken(string $login): string
    {
        $user = $this->userService->findUserByLogin($login);
        $roles = $user ? $user->getRoles() : [];
        $tokenData = [
            'username' => $login,
            'roles' => $roles,
            'exp' => time() + $this->tokenTTL,
        ];

        return $this->jwtEncoder->encode($tokenData);
    }
    ```
1. Перевыпускаем токен запросом Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию
   запроса Get user list и выполняем его, получаем результат
1. Удалям у пользователя в БД роль `ROLE_ADMIN`
1. Выполняем запрос Get user list и видим результат, хоть роль и была удалена в БД
1. Ещё раз перевыпускаем токен запросом Get token из Postman-коллекции v4, полученный токен заносим в Bearer-авторизацию
   запроса Get user list и выполняем его, получаем результат
