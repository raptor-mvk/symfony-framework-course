<?php

namespace App\DTO;

class AddFollowersDTO
{
    private array $payload;

    public function __construct(int $userId, string $followerLogin, int $count)
    {
        $this->payload = ['userId' => $userId, 'followerLogin' => $followerLogin, 'count' => $count];
    }

    public function toAMQPMessage(): string
    {
        return json_encode($this->payload);
    }
}
