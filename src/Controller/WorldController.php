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
        $author = $this->userService->create('J.R.R. Tolkien');
        $follower = $this->userService->create('Ivan Ivanov');
        $this->userService->subscribeUser($author, $follower);
        $this->userService->addSubscription($author, $follower);

        return $this->json([$author->toArray(), $follower->toArray()]);
    }
}
