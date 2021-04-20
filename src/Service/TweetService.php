<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Tweet;
use App\Entity\User;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class TweetService
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function saveTweet(int $authorId, string $text): ?Tweet {
        $tweet = new Tweet();
        $userRepository = $this->entityManager->getRepository(User::class);
        $author = $userRepository->find($authorId);
        if (!($author instanceof User)) {
            return null;
        }
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $this->entityManager->persist($tweet);
        $this->entityManager->flush();

        return $tweet;
    }

    /**
     * @param int[] $authorIds
     *
     * @return Tweet[]
     */
    public function getFeed(array $authorIds, int $count): array {
        /** @var TweetRepository $tweetRepository */
        $tweetRepository = $this->entityManager->getRepository(Tweet::class);

        return $tweetRepository->getByAuthorIds($authorIds, $count);
    }
}