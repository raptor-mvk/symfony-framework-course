<?php
declare(strict_types=1);

namespace App\Consumer\SendSmsNotificationConsumer\Input;

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
     * @Assert\Length(max="60")
     */
    private $text;

    public static function createFromQueue(string $messageBody): self
    {
        $message = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
        $result = new self();
        $result->userId = $message['userId'];
        $result->text = $message['text'];

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
    public function getText(): string
    {
        return $this->text;
    }
}
