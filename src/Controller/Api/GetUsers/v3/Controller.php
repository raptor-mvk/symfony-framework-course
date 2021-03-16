<?php

namespace App\Controller\Api\GetUsers\v3;

use App\Service\UserService;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller
{
    use ControllerTrait;

    /** @var UserService */
    private $userService;

    public function __construct(UserService $userService, ViewHandlerInterface $viewHandler)
    {
        $this->userService = $userService;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Get("/api/v3/get-users")
     */
    public function getUsersAction(Request $request): Response
    {
        $perPage = $request->request->get('perPage');
        $page = $request->request->get('page');
        $users = $this->userService->getUsers($page ?? 0, $perPage ?? 20);
        $code = empty($users) ? 204 : 200;

        $context = (new Context())->setGroups(['user1', 'user2']);
        $view = $this->view(['users' => $users], $code)->setContext($context);

        return $this->handleView($view);
    }
}
