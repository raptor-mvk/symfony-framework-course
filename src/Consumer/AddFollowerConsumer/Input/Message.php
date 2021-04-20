<?php
declare(strict_types=1);

namespace App\Consumer\AddFollowerConsumer\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class Message
{
    /**
     * @var int
     *
     * @Assert\Type("numeric")
     */
    private $userId;

    /**
     * @var string
     *
     * @Assert\Type("string")
     * @Assert\Length(max="32")
     */
    private $followerLogin;

    /**
     * @var int
     *
     * @Assert\Type("numeric")
     */
    private $count;

    public static function createFromQueue(string $messageBody): self
    {
        $message = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
        $result = new self();
        $result->userId = $message['userId'];
        $result->followerLogin = $message['followerLogin'];
        $result->count = $message['count'];

        return $result;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @return string
     */
    public function getFollowerLogin(): string
    {
        return $this->followerLogin;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
