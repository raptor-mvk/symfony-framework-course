<?php
declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\DTO\SaveUserDTO;
use App\Service\SubscriptionService;
use App\Service\TweetService;
use App\Service\UserService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\View\View;
use Throwable;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Annotations\Route("/api/v1/user")
 */
final class UserController extends AbstractFOSRestController
{
    /** @var UserService */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @Annotations\Post("")
     *
     * @RequestParam(name="login")
     * @RequestParam(name="phone")
     * @RequestParam(name="email")
     * @RequestParam(name="preferEmail", requirements="0|1")
     */
    public function addUserAction(string $login, string $phone, string $email, string $preferEmail): View
    {
        $userId = $this->userService->saveUser(new SaveUserDTO($login, $phone, $email, $preferEmail === '1'));
        [$data, $code] = $userId === null ?
            [['success' => false, 400]] :
            [['success' => true, 'userId' => $userId], 200];

        return View::create($data, $code);
    }
}