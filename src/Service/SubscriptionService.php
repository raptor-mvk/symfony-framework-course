<?php

namespace App\Service;

use App\DTO\AddFollowersDTO;
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
            $phone = '+'.str_pad((string)abs(crc32($login)), 10, '0');
            $email = "$login@gmail.com";
            $preferred = random_int(0, 1) === 1 ? User::EMAIL_NOTIFICATION : User::SMS_NOTIFICATION;
            $data = compact('login', 'password', 'age', 'isActive', 'phone', 'email', 'preferred');
            $followerId = $this->userService->saveUser(new User(), new UserDTO($data));
            if ($followerId !== null) {
                $this->subscribe($user->getId(), $followerId);
                $createdFollowers++;
            }
        }

        return $createdFollowers;
    }

    /**
     * @return string[]
     */
    public function getFollowersMessages(User $user, string $followerLogin, int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = (new AddFollowersDTO($user->getId(), "$followerLogin #$i", 1))->toAMQPMessage();
        }

        return $result;
    }

    /**
     * @return User[]
     */
    public function getFollowers(int $authorId): array
    {
        $subscriptions = $this->getSubscriptionsByAuthorId($authorId);
        $mapper = static function(Subscription $subscription) {
            return $subscription->getFollower();
        };

        return array_map($mapper, $subscriptions);
    }

    /**
     * @return int[]
     */
    public function getFollowerIds(int $authorId): array
    {
        $subscriptions = $this->getSubscriptionsByAuthorId($authorId);
        $mapper = static function(Subscription $subscription) {
            return $subscription->getFollower()->getId();
        };

        return array_map($mapper, $subscriptions);
    }

    /**
     * @return Subscription[]
     */
    private function getSubscriptionsByAuthorId(int $authorId): array
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $author = $userRepository->find($authorId);
        if (!($author instanceof User)) {
            return [];
        }
        $subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
        return $subscriptionRepository->findBy(['author' => $author]) ?? [];
    }

    /**
     * @return int[]
     */
    public function getAuthorIds(int $followerId): array
    {
        $subscriptions = $this->getSubscriptionsByFollowerId($followerId);
        $mapper = static function(Subscription $subscription) {
            return $subscription->getAuthor()->getId();
        };

        return array_map($mapper, $subscriptions);
    }

    /**
     * @return Subscription[]
     */
    private function getSubscriptionsByFollowerId(int $followerId): array
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $follower = $userRepository->find($followerId);
        if (!($follower instanceof User)) {
            return [];
        }
        $subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
        return $subscriptionRepository->findBy(['follower' => $follower]) ?? [];
    }
}