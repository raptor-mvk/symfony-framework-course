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