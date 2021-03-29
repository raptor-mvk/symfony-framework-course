<?php

namespace App\Controller\Api\AddFollowers\v1;

use App\DTO\AddFollowersDTO;
use App\Service\AsyncService;
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

    private AsyncService $asyncService;

    public function __construct(SubscriptionService $subscriptionService, UserService $userService, AsyncService $asyncService, ViewHandlerInterface $viewHandler)
    {
        $this->subscriptionService = $subscriptionService;
        $this->userService = $userService;
        $this->asyncService = $asyncService;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Post("/api/v1/add-followers")
     *
     * @RequestParam(name="userId", requirements="\d+")
     * @RequestParam(name="followersLogin")
     * @RequestParam(name="count", requirements="\d+")
     * @RequestParam(name="async", requirements="0|1")
     */
    public function addFollowersAction(int $userId, string $followersLogin, int $count, int $async): Response
    {
        $user = $this->userService->findUserById($userId);
        if ($user !== null) {
            if ($async === 0) {
                $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
                $view = $this->view(['created' => $createdFollowers], 200);
            } else {
                $message = $this->subscriptionService->getFollowersMessages($user, $followersLogin, $count);
                $result = $this->asyncService->publishMultipleToExchange(AsyncService::ADD_FOLLOWER, $message);
                $view = $this->view(['success' => $result], $result ? 200 : 500);
            }
        } else {
            $view = $this->view(['success' => false], 404);
        }

        return $this->handleView($view);
    }
}
