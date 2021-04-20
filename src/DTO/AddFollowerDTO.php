<?php
declare(strict_types=1);

namespace App\DTO;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class AddFollowerDTO
{
    /** @var array */
    private $payload;

    public function __construct(int $userId, string $followerLogin, int $count)
    {
        $this->payload = ['userId' => $userId, 'followerLogin' => $followerLogin, 'count' => $count];
    }

    public function toAMQPMessage(): string
    {
        return json_encode($this->payload);
    }
}
