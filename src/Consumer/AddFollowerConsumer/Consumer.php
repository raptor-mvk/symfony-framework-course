<?php
declare(strict_types=1);

namespace App\Consumer\AddFollowerConsumer;

use App\Consumer\AddFollowerConsumer\Input\Message;
use App\Entity\User;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
    /** @var SubscriptionService */
    private $subscriptionService;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, SubscriptionService $subscriptionService)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->subscriptionService = $subscriptionService;
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

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->find($message->getUserId());
        if (!($user instanceof User)) {
            return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
        }

        if ($message->getFollowerLogin() === 'multi_follower #11') {
            throw new Exception('Planned error');
        }
        $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());
        sleep(1);

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
