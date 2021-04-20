<?php
declare(strict_types=1);

namespace App\Service;

use App\DTO\SaveUserDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class UserService
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveUser(SaveUserDTO $saveUserDTO): ?int
    {
        $user = $saveUserDTO->toEntity(new User());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }

    public function findByLogin(string $login): ?User
    {
        /** @var EntityRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->findOneBy(['login' => $login]);

        return $user;
    }

    public function findById(int $userId): ?User
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->find($userId);

        return $user;
    }
}