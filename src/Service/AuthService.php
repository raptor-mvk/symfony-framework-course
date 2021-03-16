<?php

namespace App\Service;

use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AuthService
{
    private UserService $userService;
    private UserPasswordEncoderInterface $passwordEncoder;

    public function __construct(UserService $userService, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->userService = $userService;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function isCredentialsValid(string $login, string $password): bool
    {
        $user = $this->userService->findUserByLogin($login);
        if ($user === null) {
            return false;
        }

        return $this->passwordEncoder->isPasswordValid($user, $password);
    }

    public function getToken(string $login): ?string
    {
        return $this->userService->updateUserToken($login);
    }
}
