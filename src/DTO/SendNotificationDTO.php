<?php
declare(strict_types=1);

namespace App\DTO;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class SendNotificationDTO
{
    /** @var array */
    private $payload;

    public function __construct(int $userId, string $text)
    {
        $this->payload = ['userId' => $userId, 'text' => $text];
    }

    public function toAMQPMessage(): string
    {
        return json_encode($this->payload);
    }
}
