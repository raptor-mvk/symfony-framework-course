<?php

namespace App\Controller\Api\SaveUser\v4;

use App\Controller\Api\SaveUser\v4\Input\SaveUserDTO;
use App\Controller\Api\SaveUser\v4\Output\UserIsSavedDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class SaveUserManager
{
    private EntityManagerInterface $entityManager;

    private SerializerInterface $serializer;

    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, SerializerInterface $serializer, LoggerInterface $elasticsearchLogger)
    {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->logger = $elasticsearchLogger;
    }

    public function saveUser(SaveUserDTO $saveUserDTO): UserIsSavedDTO
    {
        $user = new User();
        $user->setLogin($saveUserDTO->login);
        $user->setPassword($saveUserDTO->password);
        $user->setRoles($saveUserDTO->roles);
        $user->setAge($saveUserDTO->age);
        $user->setIsActive($saveUserDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->logger->info("User #{$user->getId()} is saved: [{$user->getLogin()}, {$user->getAge()} yrs]");

        $result = new UserIsSavedDTO();
        $context = (new SerializationContext())->setGroups(['user1', 'user2']);
        $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));

        return $result;
    }
}
