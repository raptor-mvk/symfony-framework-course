<?php

namespace App\Service;

use App\Entity\SmsNotification;
use Doctrine\ORM\EntityManagerInterface;

final class SmsNotificationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveSmsNotification(string $phone, string $text): void {
        $smsNotification = new SmsNotification();
        $smsNotification->setPhone($phone);
        $smsNotification->setText($text);
        $this->entityManager->persist($smsNotification);
        $this->entityManager->flush();
    }
}