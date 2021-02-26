1. Запускаем контейнеры командой `docker-compose up`
1. Создаём класс `App\Repository\UserRepository`
   ```php
    <?php
    
    namespace App\Repository;
    
    use App\Entity\User;
    use Doctrine\ORM\EntityRepository;
    
    class UserRepository extends EntityRepository
    {
        /**
         * @return User[]
         */
        public function getUsers(int $page, int $perPage): array
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
1. Исправляем в классе `App\Entity\User` аннотации класса
    ```php
    /**
     * @ORM\Table(name="`user`")
     * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
     */
    ```
1. Исправляем класс `App\Service\UserService`
    ```php
    <?php
    
    namespace App\Service;
    
    use App\Entity\User;
    use App\Repository\UserRepository;
    use Doctrine\ORM\EntityManagerInterface;
    
    class UserService
    {
        /** @var EntityManagerInterface */
        private $entityManager;
    
        public function __construct(EntityManagerInterface $entityManager)
        {
            $this->entityManager = $entityManager;
        }
    
        public function saveUser(string $login): ?int
        {
            $user = new User();
            $user->setLogin($login);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
    
            return $user->getId();
        }
    
        public function updateUser(int $userId, string $login): bool
        {
            /** @var UserRepository $userRepository */
            $userRepository = $this->entityManager->getRepository(User::class);
            /** @var User $user */
            $user = $userRepository->find($userId);
            if ($user === null) {
                return false;
            }
            $user->setLogin($login);
            $this->entityManager->flush();
    
            return true;
        }
    
        public function deleteUser(int $userId): bool
        {
            /** @var UserRepository $userRepository */
            $userRepository = $this->entityManager->getRepository(User::class);
            /** @var User $user */
            $user = $userRepository->find($userId);
            if ($user === null) {
                return false;
            }
            $this->entityManager->remove($user);
            $this->entityManager->flush();
    
            return true;
        }
    
        /**
         * @return User[]
         */
        public function getUsers(int $page, int $perPage): array
        {
            /** @var UserRepository $userRepository */
            $userRepository = $this->entityManager->getRepository(User::class);
    
            return $userRepository->getUsers($page, $perPage);
        }
    }
    ```
1. Создаём класс `App\Controller\v1\UserController`
    ```php
    <?php
    
    namespace App\Controller\Api\v1;
    
    use App\Service\UserService;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Annotation\Route;
    
    /** @Route("/api/v1/user") */
    class UserController
    {
        /** @var UserService */
        private $userService;
    
        public function __construct(UserService $userService)
        {
            $this->userService = $userService;
        }
    
        /**
         * @Route("", methods={"POST"})
         */
        public function saveUserAction(Request $request): Response
        {
            $login = $request->request->get('login');
            $userId = $this->userService->saveUser($login);
            [$data, $code] = $userId === null ?
                [['success' => false], 400] :
                [['success' => true, 'userId' => $userId], 200];
    
            return new JsonResponse($data, $code);
        }
    
        /**
         * @Route("", methods={"GET"})
         */
        public function getUsersAction(Request $request): Response
        {
            $perPage = $request->query->get('perPage');
            $page = $request->query->get('page');
            $users = $this->userService->getUsers($page ?? 0, $perPage ?? 20);
            $code = empty($users) ? 204 : 200;
    
            return new JsonResponse(['users' => $users], $code);
        }
    
        /**
         * @Route("", methods={"DELETE"})
         */
        public function deleteUserAction(Request $request): Response
        {
            $userId = $request->query->get('userId');
            $result = $this->userService->deleteUser($userId);
    
            return new JsonResponse(['success' => $result], $result ? 200 : 404);
        }
    
        /**
         * @Route("", methods={"PATCH"})
         */
        public function updateUserAction(Request $request): Response
        {
            $userId = $request->request->get('userId');
            $login = $request->request->get('login');
            $result = $this->userService->updateUser($userId, $login);
    
            return new JsonResponse(['success' => $result], $result ? 200 : 404);
        }
    }
    ```
1. Заходим в контейнер `php` командой `docker exec -it php sh`, далее все команды выполняются в контейнере
1. Выполняем команду `php bin/console debug:router`, видим список наших endpoint'ов из контроллера
1. Выполняем запрос Add user из Postman-коллекции, видим, что пользователь добавился
1. Выполняем запрос Delete user из Postman-коллекции с id из результата предыдущего запроса, видим, что пользователь
   удалился
1. Исправляем в классе `App\Controller\Api\v1\UserController` метод `deleteUserAction`
    ```php
    /**
    * @Route("/{id}", methods={"DELETE"}, requirements={"id":"\d+"})
    */
    public function deleteUserAction(int $id): Response
    {
       $result = $this->userService->deleteUser($id);
       
       return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }
    ```
1. Ещё раз выполняем запрос Add user из Postman-коллекции, чтобы создать пользователя
1. В запросе Delete user заменяем URL на /api/v1/user/ID, где ID - id из результата предыдущего запроса
1. Ещё раз выполняем запрос Add user из Postman-коллекции, чтобы создать пользователя
1. Пробуем отправить запрос Patch user из Postman-коллекции для созданного в предыдущем запросе пользователя, видим
   ошибку 502
1. Добавляем новую переменную в файл `.env`
    ```shell
    SHELL_VERBOSITY=-1
    ```
1. Ещё раз пробуем отправить запрос Patch user из Postman-коллекции, видим 500-ю ошибку
1. Переносим в PATCH-запросе параметры из тела в строку запроса
1. Исправляем в классе `App\Controller\Api\v1\UserController` метод `updateUserAction`
    ```php
    /**
     * @Route("", methods={"PATCH"})
     */
    public function updateUserAction(Request $request): Response
    {
        $userId = $request->query->get('userId');
        $login = $request->query->get('login');
        $result = $this->userService->updateUser($userId, $login);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }
    ```
1. Ещё раз пробуем отправить запрос Patch user из Postman-коллекции, логин обновляется
1. Отправляем запрос Get user list из Postman-коллекции, видим пустой список пользователей
1. Исправляем в классе `App\Controller\Api\v1\UserController` метод `getUsersAction`
    ```php
    /**
     * @Route("", methods={"GET"})
     */
    public function getUsersAction(Request $request): Response
    {
        $perPage = $request->query->get('perPage');
        $page = $request->query->get('page');
        $users = $this->userService->getUsers($page ?? 0, $perPage ?? 20);
        $code = empty($users) ? 204 : 200;

        return new JsonResponse(['users' => array_map(static fn(User $user) => $user->toArray(), $users)], $code);
    }
    ```
1. Ещё раз отправляем запрос Get user list из Postman-коллекции, видим список пользователей с данными
1. Устанавливаем пакет `sensio/framework-extra-bundle` командой `composer require sensio/framework-extra-bundle`
1. Устанавливаем пакет `symfony/expression-language` командой `composer require symfony/expression-language` (
   понадобится для использования аннотации `@Entity`)
1. Создаём класс `App\Controller\Api\v2\UserController`
    ```php
    <?php
    
    namespace App\Controller\Api\v2;
    
    use App\Entity\User;
    use App\Service\UserService;
    use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
    use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
    use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
    use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    /**
     * @Route("/api/v2/user", service="App\Controller\Api\v2\UserController")
     */
    class UserController
    {
        /** @var UserService */
        private $userService;
    
        public function __construct(UserService $userService)
        {
            $this->userService = $userService;
        }
    
        /**
         * @Route("")
         * @Method("POST")
         */
        public function saveUserAction(Request $request): Response
        {
            $login = $request->request->get('login');
            $userId = $this->userService->saveUser($login);
            [$data, $code] = $userId === null ?
                [['success' => false], 400] :
                [['success' => true, 'userId' => $userId], 200];
    
            return new JsonResponse($data, $code);
        }
    
        /**
         * @Route("")
         * @Method("GET")
         */
        public function getUsersAction(Request $request): Response
        {
            $perPage = $request->request->get('perPage');
            $page = $request->request->get('page');
            $users = $this->userService->getUsers($page ?? 0, $perPage ?? 20);
            $code = empty($users) ? 204 : 200;
    
            return new JsonResponse(['users' => array_map(static fn(User $user) => $user->toArray(), $users)], $code);
        }
    
        /**
         * @Route("/by-login/{user_login}", priority=2)
         * @Method("GET")
         * @ParamConverter("user", options={"mapping": {"user_login": "login"}})
         */
        public function getUserByLoginAction(User $user): Response
        {
            return new JsonResponse(['user' => $user], 200);
        }
    
        /**
         * @Route("/{user_id}")
         * @Method("DELETE")
         * @Entity("user", expr="repository.find(user_id)")
         */
        public function deleteUserAction(User $user): Response
        {
            $result = $this->userService->deleteUser($user);
    
            return new JsonResponse(['success' => $result], $result ? 200 : 404);
        }
    
        /**
         * @Route("")
         * @Method("PATCH")
         */
        public function updateUserAction(Request $request): Response
        {
            $userId = $request->request->get('userId');
            $login = $request->request->get('login');
            $result = $this->userService->updateUser($userId, $login);
    
            return new JsonResponse(['success' => $result->toArray()], $result ? 200 : 404);
        }
    }
    ```
1. Исправляем в классе `App\Controller\Api\v1\UserController` метод `deleteUserAction`
    ```php
    /**
     * @Route("/{id}", methods={"DELETE"}, requirements={"id":"\d+"})
     */
    public function deleteUserAction(int $id): Response
    {
        $result = $this->userService->deleteUserById($id);

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }
    ```
1. Исправляем класс `App\Service\UserService`
   1. Переименовываем метод `deletUser` в `deleteUserById`
   1. Добавляем новый метод `deleteUser`
        ```php
        public function deleteUser(User $user): bool
        {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
   
            return true;
        }
        ```
   1. Исправляем метод `deleteUserById`
        ```php
        public function deleteUserById(int $userId): bool
        {
            /** @var UserRepository $userRepository */
            $userRepository = $this->entityManager->getRepository(User::class);
            /** @var User $user */
            $user = $userRepository->find($userId);
            if ($user === null) {
                return false;
            }
            return $this->deleteUser($user);
        }
        ```
1. В запросе Add user из Postman-коллекции заменяем версию API на 2-ю и выполняем его, чтобы создать пользователя
1. В запросе Delete user из Postman-коллекции заменяем версию API на 2-ю и выполняем его с id из результата предыдущего
   запроса
1. Вызываем запрос Get user by login v2 из Postman-коллекции, видим, что пользователь находится по логину