<?php
declare(strict_types=1);

namespace App\Consumer\PublishTweetConsumer\Output;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class UpdateFeedMessage
{
    /**
     * @var array
     */
    private $payload;

    /**
     * Message constructor.
     * @param array $payload
     */
    public function __construct(int $tweetId, int $followerId)
    {
        $this->payload = ['tweetId' => $tweetId, 'followerId' => $followerId];
    }

    public function toAMQPMessage(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR, 512);
    }
}
