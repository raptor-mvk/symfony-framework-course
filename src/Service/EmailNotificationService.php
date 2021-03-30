<?php

namespace App\Service;

use App\Entity\EmailNotification;
use Doctrine\ORM\EntityManagerInterface;

class EmailNotificationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveEmailNotification(string $email, string $text): void {
        $emailNotification = new EmailNotification();
        $emailNotification->setEmail($email);
        $emailNotification->setText($text);
        $this->entityManager->persist($emailNotification);
        $this->entityManager->flush();
    }
}