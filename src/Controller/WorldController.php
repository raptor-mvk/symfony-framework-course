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
        $author = $this->userService->create('Charles Dickens');
        $this->userService->postTweet($author, 'Oliver Twist');
        $this->userService->postTweet($author, 'The Christmas Carol');
        $userData = $this->userService->findUserWithTweetsWithQueryBuilder($author->getId());

        return $this->json($userData);
    }
}
