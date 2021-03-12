<?php

namespace App\Controller\Api\SaveUser\v4;

use App\Controller\Api\SaveUser\v4\Input\SaveUserDTO;
use App\Controller\Common\ErrorResponseTrait;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class Controller
{
    use ControllerTrait, ErrorResponseTrait;

    private SaveUserManager $saveUserManager;

    public function __construct(SaveUserManager $saveUserManager, ViewHandlerInterface $viewHandler)
    {
        $this->saveUserManager = $saveUserManager;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Post("/api/v4/save-user")
     */
    public function saveUserAction(SaveUserDTO $request, ConstraintViolationListInterface $validationErrors): Response
    {
        if ($validationErrors->count()) {
            $view = $this->createValidationErrorResponse(Response::HTTP_BAD_REQUEST, $validationErrors);
            return $this->handleView($view);
        }
        $user = $this->saveUserManager->saveUser($request);
        [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];
        return $this->handleView($this->view($data, $code));
    }
}
