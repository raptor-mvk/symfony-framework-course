<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailNotification;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class EmailNotificationService
{
    /** @var EntityManagerInterface */
    private $entityManager;

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