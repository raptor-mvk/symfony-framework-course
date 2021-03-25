<?php

namespace App\Controller\Api\AddFollowers\v1;

use App\Service\SubscriptionService;
use App\Service\UserService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Response;

class Controller
{
    use ControllerTrait;

    private SubscriptionService $subscriptionService;

    private UserService $userService;

    public function __construct(SubscriptionService $subscriptionService, UserService $userService, ViewHandlerInterface $viewHandler)
    {
        $this->subscriptionService = $subscriptionService;
        $this->userService = $userService;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Post("/api/v1/add-followers")
     *
     * @RequestParam(name="userId", requirements="\d+")
     * @RequestParam(name="followersLogin")
     * @RequestParam(name="count", requirements="\d+")
     */
    public function addFollowersAction(int $userId, string $followersLogin, int $count): Response
    {
        $user = $this->userService->findUserById($userId);
        if ($user !== null) {
            $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
            $view = $this->view(['created' => $createdFollowers], 200);
        } else {
            $view = $this->view(['success' => false], 404);
        }

        return $this->handleView($view);
    }
}
