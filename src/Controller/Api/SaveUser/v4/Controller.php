<?php

namespace App\Controller\Api\SaveUser\v4;

use StatsdBundle\Client\StatsdAPIClient;
use App\Controller\Api\SaveUser\v4\Input\SaveUserDTO;
use App\Controller\Common\ErrorResponseTrait;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class Controller
{
    use ControllerTrait, ErrorResponseTrait;

    private SaveUserManager $saveUserManager;

    private LoggerInterface $logger;

    private StatsdAPIClient $statsdAPIClient;

    public function __construct(SaveUserManager $saveUserManager, ViewHandlerInterface $viewHandler, LoggerInterface $logger, StatsdAPIClient $statsdAPIClient)
    {
        $this->saveUserManager = $saveUserManager;
        $this->viewhandler = $viewHandler;
        $this->logger = $logger;
        $this->statsdAPIClient = $statsdAPIClient;
    }

    /**
     * @Rest\Post("/api/v4/save-user")
     */
    public function saveUserAction(SaveUserDTO $request, ConstraintViolationListInterface $validationErrors): Response
    {
        $this->statsdAPIClient->increment('save_user_v4_attempt');
        $this->logger->debug('This is debug message');
        $this->logger->info('This is info message');
        $this->logger->notice('This is notice message');
        $this->logger->warning('This is warning message');
        $this->logger->error('This is error message');
        $this->logger->critical('This is critical message');
        $this->logger->alert('This is alert message');
        $this->logger->emergency('This is emergency message');
        if ($validationErrors->count()) {
            $view = $this->createValidationErrorResponse(Response::HTTP_BAD_REQUEST, $validationErrors);
            return $this->handleView($view);
        }
        $user = $this->saveUserManager->saveUser($request);
        [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];
        return $this->handleView($this->view($data, $code));
    }
}
