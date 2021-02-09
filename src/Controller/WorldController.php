<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function hello(): Response
    {
        return $this->render('user-vue.twig', ['users' => json_encode($this->userService->getUsersListVue())]);
    }
}
