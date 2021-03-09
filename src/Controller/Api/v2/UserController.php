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
        return new JsonResponse(['user' => $user->toArray()], 200);
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

        return new JsonResponse(['success' => $result], $result ? 200 : 404);
    }
}