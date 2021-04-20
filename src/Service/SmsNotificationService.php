<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\SmsNotification;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class SmsNotificationService
{
    /** @var EntityManagerInterface */
    private $entityManager;

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