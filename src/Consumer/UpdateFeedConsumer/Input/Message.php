<?php
declare(strict_types=1);

namespace App\Consumer\UpdateFeedConsumer\Input;

use Symfony\Component\Validator\Constraints;

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
     * @Constraints\Regex("/^\d+$/")
     */
    private $tweetId;

    /**
     * @var int
     *
     * @Constraints\Regex("/^\d+$/")
     */
    private $followerId;

    public static function createFromQueue(string $messageBody): self
    {
        $message = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
        $result = new self();
        $result->tweetId = $message['tweetId'];
        $result->followerId = $message['followerId'];

        return $result;
    }

    /**
     * @return int
     */
    public function getTweetId(): int
    {
        return $this->tweetId;
    }

    /**
     * @return int
     */
    public function getFollowerId(): int
    {
        return $this->followerId;
    }
}
