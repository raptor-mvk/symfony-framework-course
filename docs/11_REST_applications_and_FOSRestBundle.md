1. Запускаем контейнеры командой `docker-compose up -d`
1. Устанавливаем пакеты `jms/serializer-bundle` и `friendsofsymfony/rest-bundle`
1. В файле `config/packages/fos_rest.yaml` удаляем `null` и раскомментируем строки
    ```yaml
    view:
        view_response_listener:  true

    format_listener:
        rules:
            - { path: ^/api, prefer_extension: true, fallback_format: json, priorities: [ json, html ] }
    ```
1. Добавляем класс `Controller\Api\GetUsers\v3\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\GetUsers\v3;
    
    use App\Service\UserService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller
    {
        use ControllerTrait;
    
        /** @var UserService */
        private $userService;
    
        public function __construct(UserService $userService, ViewHandlerInterface $viewHandler)
        {
            $this->userService = $userService;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Get("/api/v3/get-users")
         */
        public function getUsersAction(Request $request): Response
        {
            $perPage = $request->request->get('perPage');
            $page = $request->request->get('page');
            $users = $this->userService->getUsers($page ?? 0, $perPage ?? 20);
            $code = empty($users) ? 204 : 200;
    
            return $this->handleView($this->view(['users' => $users], $code));
        }
    }
    ```
1. Выполняем запрос Get user list v3 из Postman-коллекции v3, видим, что возвращается список пользователей, хотя мы
   не выполняем явно `toArray` для каждого из них
1. В классе `App\Entity\User` добавляем два новых поля `$age` и `$isActive`, а также стандартные геттеры и сеттеры для
   них
    ```php
    /**
     * @ORM\Column(type="integer", nullable=true)
     * @JMS\Type("string")
     */
    private int $age;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @JMS\Type("int")
     */
    private bool $isActive;
    ```
1. Входим в контейнер командой `docker exec -it php sh`
1. Готовим миграцию командой `php bin/console doctrine:migrations:diff`
1. Готовим миграцию командой `php bin/console doctrine:migrations:migrate`
1. Заполняем какими-нибудь значениями в БД новые поля для существующих записей.
1. Выполняем запрос Get user list v3 из Postman-коллекции v3 и видим, что типы данных в сериализованном ответе
   отличаются от типов данных в БД
1. В классе `App\Entity\User` добавляем аннотации группы для полей `$login`, `$age` и `$isActive`
    ```php
    /**
     * @ORM\Column(type="string", length=32, nullable=false, unique=true)
     * @JMS\Groups({"user1"})
     */
    private string $login;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @JMS\Type("string")
     * @JMS\Groups({"user1"})
     */
    private int $age;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @JMS\Type("int")
     * @JMS\Groups({"user1"})
     */
    private bool $isActive;
    ```
1. В классе `App\Controller\GetUsers\v3\Controller` заменяем последнюю строку на
    ```php
    $context = (new Context())->setGroups(['user1']);
    $view = $this->view(['users' => $users], $code)->setContext($context);

    return $this->handleView($view);
    ```
1. Выполняем запрос Get user list v3 из Postman-коллекции v3 и видим, что отдаются только аннотированные поля
1. Добавляем аннотацию другой группы для поля `$id`
    ```php
    /**
     * @ORM\Column(name="id", type="bigint", unique=true)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @JMS\Groups({"user2"})
     */
    private ?int $id = null;
    ```
1. В классе `App\Controller\GetUsers\v3\Controller` добавляем в контекст ещё одну группу
    ```php
    $context = (new Context())->setGroups(['user1', 'user2']);
    ```
1. Выполняем запрос Get user list v3 из Postman-коллекции v3 и видимо, что добавилось поле `id` в ответ
1. Исправляем класс `App\DTO\UserDTO`
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
        
        public ?int $age;
        
        public ?bool $isActive;
    
        /**
         * @throws JsonException
         */
        public function __construct(array $data)
        {
            $this->login = $data['login'] ?? '';
            $this->password = $data['password'] ?? '';
            $this->roles = json_decode($data['roles'] ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?? [];
            $this->age = $data['age'] ?? null;
            $this->isActive = $data['isActive'] ?? null;
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
                'age' => $user->getAge(),
                'isActive' => $user->isActive(),
            ]);
        }
    }
    ```
1. Исправляем в классе `App\Service\UserService` метод `saveUser`
    ```php
    /**
     * @throws JsonException
     */
    public function saveUser(User $user, UserDTO $userDTO): ?int
    {
        $user->setLogin($userDTO->login);
        $user->setPassword($this->userPasswordEncoder->encodePassword($user, $userDTO->password));
        $user->setRoles($userDTO->roles);
        $user->setAge($userDTO->age);
        $user->setIsActive($userDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }
    ```
1. Добавляем класс `Controller\Api\SaveUser\v3\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveUser\v3;
    
    use App\Entity\User;
    use App\Service\UserService;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\RequestParam;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    use App\DTO\UserDTO;
    
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
         * @Rest\Post("/api/v3/save-user")
         *
         * @RequestParam(name="login")
         * @RequestParam(name="password")
         * @RequestParam(name="roles")
         * @RequestParam(name="age", requirements="\d+")
         * @RequestParam(name="isActive", requirements="true|false")
         */
        public function saveUserAction(string $login, string $password, $age, $isActive): Response
        {
            $userDTO = new UserDTO([
                    'login' => $login,
                    'password' => $password,
                    'age' => (int)$age,
                    'isActive' => $isActive === 'true']
            );
            $userId = $this->userService->saveUser(new User(), $userDTO);
            [$data, $code] = ($userId === null) ? [['success' => false], 400] : [['id' => $userId], 200];
            return $this->handleView($this->view($data, $code));
        }
    }
    ```
1. В файле `config/packages/fos_rest.yaml` добавляем строку
     ```yaml
     param_fetcher_listener:  force
     ```
1. Выполняем запрос Add user v3 из Postman-коллекции v3, видим, что пользователь добавился
1. Устанавливаем пакет `symfony/options-resolver`
1. В файл `config/services.yaml` в секцию `App\Controller\` добавляем строку
    ```yaml
    exclude: '../src/Controller/Common/*'
    ```
1. Добавляем класс `App\Controller\Common\Error`
    ```php
    <?php
    
    namespace App\Controller\Common;
    
    class Error
    {
        public string $propertyPath;
    
        public string $message;
    
        public function __construct(string $propertyPath, string $message)
        {
            $this->propertyPath = $propertyPath;
            $this->message = $message;
        }
    }
    ```
1. Добавляем класс `App\Controller\Common\ErrorResponse`
    ```php
    <?php
    
    namespace App\Controller\Common;
    
    class ErrorResponse
    {
        public bool $success = false;
    
        /** @var Error[] */
        public array $errors;
    
        public function __construct(Error ...$errors)
        {
            $this->errors = $errors;
        }
    }
    ```
1. Добавляем трейт `App\Controller\Common\ErrorResponseTrait`
    ```php
    <?php
    
    namespace App\Controller\Common;
    
    use FOS\RestBundle\View\View;
    use Symfony\Component\Validator\ConstraintViolationInterface;
    use Symfony\Component\Validator\ConstraintViolationListInterface;
    
    trait ErrorResponseTrait
    {
        private function createValidationErrorResponse(int $code, ConstraintViolationListInterface $validationErrors): View
        {
            $errors = [];
            foreach ($validationErrors as $error) {
                /** @var ConstraintViolationInterface $error */
                $errors[] = new Error($error->getPropertyPath(), $error->getMessage());
            }
            return View::create(new ErrorResponse(...$errors), $code);
        }
    }
    ```
1. Добавляем трейт `App\Entity\Traits\SafeLoadFieldsTrait`
    ```php
    <?php
    
    namespace App\Entity\Traits;
    
    use Symfony\Component\HttpFoundation\Request;
    
    trait SafeLoadFieldsTrait
    {
        abstract public function getSafeFields(): array;
    
        public function loadFromJsonString(string $json): void
        {
            $this->loadFromArray(json_decode($json, true));
        }
    
        public function loadFromJsonRequest(Request $request): void
        {
            $this->loadFromJsonString($request->getContent());
        }
    
        public function loadFromArray(?array $input): void
        {
            if (empty($input)) {
                return;
            }
            $safeFields = $this->getSafeFields();
    
            foreach ($safeFields as $field) {
                if (array_key_exists($field, $input)) {
                    $this->{$field} = $input[$field];
                }
            }
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\SaveUser\v4\Input\SaveUserDTO`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveUser\v4\Input;
    
    use App\Entity\Traits\SafeLoadFieldsTrait;
    use Symfony\Component\Validator\Constraints as Assert;
    
    class SaveUserDTO
    {
        use SafeLoadFieldsTrait;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("string")
         * @Assert\Length(max=32)
         */
        public string $login;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("string")
         * @Assert\Length(max=32)
         */
        public string $password;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("array")
         */
        public array $roles;

        /**
         * @Assert\NotBlank()
         * @Assert\Type("numeric")
         */
        public int $age;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("bool")
         */
        public bool $isActive;
    
        public function getSafeFields(): array
        {
            return ['login', 'password', 'roles', 'age', 'isActive'];
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\SaveUser\v4\Output\UserIsSavedDTO`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveUser\v4\Output;
    
    use App\Entity\Traits\SafeLoadFieldsTrait;
    use Symfony\Component\Validator\Constraints as Assert;
    
    class UserIsSavedDTO
    {
        use SafeLoadFieldsTrait;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("numeric")
         */
        public int $id;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("string")
         * @Assert\Length(max=32)
         */
        public string $login;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("numeric")
         */
        public int $age;
    
        /**
         * @Assert\NotBlank()
         * @Assert\Type("bool")
         */
        public bool $isActive;
    
        public function getSafeFields(): array
        {
            return ['id', 'login', 'age', 'isActive'];
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\SaveUser\v4\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveUser\v4;
    
    use App\Controller\Api\SaveUser\v4\Input\SaveUserDTO;
    use App\Controller\Common\ErrorResponseTrait;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\ControllerTrait;
    use FOS\RestBundle\View\ViewHandlerInterface;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Validator\ConstraintViolationListInterface;
    
    class Controller
    {
        use ControllerTrait, ErrorResponseTrait;
    
        private SaveUserManager $saveUserManager;
    
        public function __construct(SaveUserManager $saveUserManager, ViewHandlerInterface $viewHandler)
        {
            $this->saveUserManager = $saveUserManager;
            $this->viewhandler = $viewHandler;
        }
    
        /**
         * @Rest\Post("/api/v4/save-user")
         */
        public function saveUserAction(SaveUserDTO $request, ConstraintViolationListInterface $validationErrors): Response
        {
            if ($validationErrors->count()) {
                $view = $this->createValidationErrorResponse(Response::HTTP_BAD_REQUEST, $validationErrors);
                return $this->handleView($view);
            }
            $user = $this->saveUserManager->saveUser($request);
            [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];
            return $this->handleView($this->view($data, $code));
        }
    }
    ```
1. Добавляем класс `App\Controller\Api\SaveUser\v4\SaveUserManager`
    ```php
    <?php
    
    namespace App\Controller\Api\SaveUser\v4;
    
    use App\Controller\Api\SaveUser\v4\Input\SaveUserDTO;
    use App\Controller\Api\SaveUser\v4\Output\UserIsSavedDTO;
    use App\Entity\User;
    use Doctrine\ORM\EntityManagerInterface;
    use JMS\Serializer\SerializationContext;
    use JMS\Serializer\SerializerInterface;
    
    class SaveUserManager
    {
        private EntityManagerInterface $entityManager;

        private SerializerInterface $serializer;
    
        public function __construct(EntityManagerInterface $entityManager, SerializerInterface $serializer)
        {
            $this->entityManager = $entityManager;
            $this->serializer = $serializer;
        }
    
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
    
            $result = new UserIsSavedDTO();
            $context = (new SerializationContext())->setGroups(['user1', 'user2']);
            $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));
    
            return $result;
        }
    
    }
    ```
1. Добавляем класс `App\Symfony\MainParamConverter`
    ```php
    <?php
    
    namespace App\Symfony;
    
    use App\Entity\Traits\SafeLoadFieldsTrait;
    use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
    use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\OptionsResolver\OptionsResolver;
    use Symfony\Component\Validator\ConstraintViolationListInterface;
    use Symfony\Component\Validator\Validator\ValidatorInterface;
    
    class MainParamConverter implements ParamConverterInterface
    {
        private ValidatorInterface $validator;
    
        public function __construct(ValidatorInterface $validator)
        {
            $this->validator = $validator;
        }
    
        public function apply(Request $httpRequest, ParamConverter $configuration): bool
        {
            $class = $configuration->getClass();
            /** @var SafeLoadFieldsTrait $request */
            $request = new $class();
            $request->loadFromJsonRequest($httpRequest);
            $errors = $this->validate($request, $httpRequest, $configuration);
            $httpRequest->attributes->set('validationErrors', $errors);
    
            return true;
        }
    
        public function supports(ParamConverter $configuration): bool
        {
            return !empty($configuration->getClass()) &&
                in_array(SafeLoadFieldsTrait::class, class_uses($configuration->getClass()), true);
        }
    
        public function validate($request, Request $httpRequest, ParamConverter $configuration): ConstraintViolationListInterface
        {
            $httpRequest->attributes->set($configuration->getName(), $request);
            $options = (array)$configuration->getOptions();
            $resolver = new OptionsResolver();
            $resolver->setDefaults([
                'groups' => null,
                'traverse' => false,
                'deep' => false,
            ]);
            $validatorOptions = $resolver->resolve($options['validator'] ?? []);
    
            return $this->validator->validate($request, null, $validatorOptions['groups']);
        }
    }
    ```
1. В классе `App\Entity\User` возвращаем правильные типы данных в аннотациях для полей `$age` и `$isActive`, а также
   добавляем к полю `$isActive` аннотацию `@SerializedName`
    ```php
    /**
     * @ORM\Column(type="integer", nullable=true)
     * @JMS\Type("int")
     * @JMS\Groups({"user1"})
     */
    private int $age;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @JMS\Type("bool")
     * @JMS\Groups({"user1"})
     * @JMS\SerializedName("isActive")
     */
    private bool $isActive;
    ```
1. Выполняем запрос Add user v4 из Postman-коллекции v3, видим, что пользователь добавился
1. Добавляем класс `App\Exception\DeprecatedApiException`
    ```php
    <?php
    
    namespace App\Exception;
   
    use Exception;
    
    class DeprecatedApiException extends Exception
    {
    }
    ```
1. В классе `App\Controller\Api\SaveUser\v3\Controller` в начало метода `saveUserAction` добавляем
    ```php
    throw new DeprecatedApiException("Use POST /api/v4/save-user instead");
    ```
1. Добавляем класс `App\EventListener\DeprecatedApiExceptionListener`
    ```php
    <?php
    
    namespace App\EventListener;
    
    use App\Exception\DeprecatedApiException;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ExceptionEvent;
    
    class DeprecatedApiExceptionListener
    {
        public function onKernelException(ExceptionEvent $event): void
        {
            $exception = $event->getThrowable();
    
            $response = new Response();
            $response->setContent($exception->getMessage());
    
            if ($exception instanceof DeprecatedApiException) {
                $response->setStatusCode(Response::HTTP_GONE);
            } else {
                $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
    
            $event->setResponse($response);
        }
    }
    ```
1. В файл `config/services.yaml` добавляем
    ```yaml
    deprecatedApiExceptionListener:
        class: App\EventListener\DeprecatedApiExceptionListener
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
    ```
1. Выполняем запрос Add user v3 из Postman-коллекции v3, видим ответ с кодом 410 и заданным нами текстом
1. Добавляем класс `App\Exception\OtherDeprecatedApiException`
    ```php
    <?php
    
    namespace App\Exception;
   
    use Exception;
    
    class OtherDeprecatedApiException extends Exception
    {
    }
    ```
1. В классе `App\Controller\Api\SaveUser\v3\Controller` в начале метода `saveUserAction` заменяем класс исключения
    ```php
    throw new OtherDeprecatedApiException("Use POST /api/v3/user instead");
    ```
1. Добавляем класс `App\EventSubscriber\OtherDeprecatedApiExceptionSubscriber`
    ```php
    <?php
    
    namespace App\EventSubscriber;
    
    use App\Exception\OtherDeprecatedApiException;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ExceptionEvent;
    use Symfony\Component\HttpKernel\KernelEvents;
    
    class OtherDeprecatedApiExceptionSubscriber implements EventSubscriberInterface
    {
        public function onKernelException(ExceptionEvent $event): void
        {
            $exception = $event->getThrowable();
    
            $response = new Response();
            $response->setContent($exception->getMessage());
    
            if ($exception instanceof OtherDeprecatedApiException) {
                $response->setStatusCode(Response::HTTP_GONE);
            } else {
                $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
    
            $event->setResponse($response);
        }
    
        public static function getSubscribedEvents(): array
        {
            return [
                KernelEvents::EXCEPTION => 'onKernelException',
            ];
        }
    }
    ```
1. В файле `config/services.yaml` удаляем
    ```yaml
    deprecatedApiExceptionListener:
        class: App\EventListener\DeperecatedApiExceptionListener
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
    ```
1. Выполняем запрос Add user v3 из Postman-коллекции v3, снова видим ответ с кодом 410 и заданным нами текстом
