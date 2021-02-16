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
        $user = $this->userService->findUser(3);
        if ($user === null) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }
        $this->userService->updateUserLoginWithQueryBuilder($user->getId(), 'User is updated');
    
        return $this->json($user->toArray());
    }
}
