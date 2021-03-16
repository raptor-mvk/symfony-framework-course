<?php

namespace App\Service;

use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AuthService
{
    private const TOKEN_EXPIRATION = '86400';

    private UserService $userService;
    private UserPasswordEncoderInterface $passwordEncoder;
    private JWTEncoderInterface $jwtEncoder;
    private int $tokenTTL;

    public function __construct(UserService $userService, UserPasswordEncoderInterface $passwordEncoder, JWTEncoderInterface $jwtEncoder, int $tokenTTL)
    {
        $this->userService = $userService;
        $this->passwordEncoder = $passwordEncoder;
        $this->jwtEncoder = $jwtEncoder;
        $this->tokenTTL = $tokenTTL;
    }

    public function isCredentialsValid(string $login, string $password): bool
    {
        $user = $this->userService->findUserByLogin($login);
        if ($user === null) {
            return false;
        }

        return $this->passwordEncoder->isPasswordValid($user, $password);
    }

    public function getToken(string $login): string
    {
        $tokenData = ['username' => $login, 'exp' => time() + $this->tokenTTL];

        return $this->jwtEncoder->encode($tokenData);
    }
}
