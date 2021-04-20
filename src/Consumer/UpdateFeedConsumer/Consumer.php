<?php
declare(strict_types=1);

namespace App\Consumer\UpdateFeedConsumer;

use App\Client\StatsdAPIClient;
use App\Consumer\UpdateFeedConsumer\Input\Message;
use App\DTO\SendNotificationDTO;
use App\Entity\Tweet;
use App\Entity\User;
use App\Service\AsyncService;
use App\Service\FeedService;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class Consumer implements ConsumerInterface
{
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var ValidatorInterface */
    private $validator;
    /** @var FeedService */
    private $feedService;
    /** @var AsyncService */
    private $asyncService;
    /** @var StatsdAPIClient */
    private $statsdAPIClient;
    /** @var string */
    private $key;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, FeedService $feedService, AsyncService $asyncService, StatsdAPIClient $statsdAPIClient, string $key)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->feedService = $feedService;
        $this->asyncService = $asyncService;
        $this->statsdAPIClient = $statsdAPIClient;
        $this->key = $key;
    }

    public function execute(AMQPMessage $msg): int
    {
        try {
            $message = Message::createFromQueue($msg->getBody());
            $errors = $this->validator->validate($message);
            if ($errors->count() > 0) {
                return $this->reject((string)$errors);
            }
        } catch (JsonException $e) {
            return $this->reject($e->getMessage());
        }

        $tweetRepository = $this->entityManager->getRepository(Tweet::class);
        $userRepository = $this->entityManager->getRepository(User::class);
        $tweet = $tweetRepository->find($message->getTweetId());
        if (!($tweet instanceof Tweet)) {
            return $this->reject(sprintf('Tweet ID %s was not found', $message->getTweetId()));
        }

        $this->feedService->putTweet($tweet, $message->getFollowerId());
        $this->statsdAPIClient->increment($this->key);
        /** @var User $user */
        $user = $userRepository->find($message->getFollowerId());
        if ($user !== null) {
            $message = (new SendNotificationDTO($message->getFollowerId(), $tweet->getText()))->toAMQPMessage();
            $this->asyncService->publishToExchange(
                AsyncService::SEND_NOTIFICATION,
                $message,
                $user->getPreferred()
            );
        }

        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        return self::MSG_ACK;
    }

    private function reject(string $error): int
    {
        echo "Incorrect message: $error";

        return self::MSG_REJECT;
    }
}
