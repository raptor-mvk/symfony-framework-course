<?php

namespace App\Controller\Api\GetUsersByQueryWithAggregation\v1;

use App\Service\UserService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Response;

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
     * @Rest\Get("/api/v1/get-users-by-query-with-aggregation")
     *
     * @QueryParam(name="query")
     * @QueryParam(name="field")
     */
    public function getUsersByQueryWithAggregationAction(string $query, string $field): Response
    {
        return $this->handleView($this->view($this->userService->findUserByQueryWithAggregation($query, $field), 200));
    }
}