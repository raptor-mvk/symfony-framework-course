<?php

namespace App\Controller;

use App\Entity\User;
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
        $users = $this->userService->findUsersWithQueryBuilder('Lewis');

        return $this->json(array_map(static fn(User $user) => $user->toArray(), $users));
    }
}
