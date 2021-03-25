<?php

namespace App\Service;

use App\DTO\UserDTO;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionService
{
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var UserService */
    private $userService;

    public function __construct(EntityManagerInterface $entityManager, UserService $userService)
    {
        $this->userService = $userService;
        $this->entityManager = $entityManager;
    }

    public function subscribe(int $authorId, int $followerId): bool
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $author = $userRepository->find($authorId);
        if (!($author instanceof User)) {
            return false;
        }
        $follower = $userRepository->find($followerId);
        if (!($follower instanceof User)) {
            return false;
        }

        $subscription = new Subscription();
        $subscription->setAuthor($author);
        $subscription->setFollower($follower);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return true;
    }

    public function addFollowers(User $user, string $followerLogin, int $count): int
    {
        $createdFollowers = 0;
        for ($i = 0; $i < $count; $i++) {
            $login = "{$followerLogin}_#$i";
            $password = $followerLogin;
            $age = $i;
            $isActive = true;
            $data = compact('login', 'password', 'age', 'isActive');
            $followerId = $this->userService->saveUser(new User(), new UserDTO($data));
            if ($followerId !== null) {
                $this->subscribe($user->getId(), $followerId);
                $createdFollowers++;
            }
        }

        return $createdFollowers;
    }
}