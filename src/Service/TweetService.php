<?php

namespace App\Service;

use App\Entity\Tweet;
use App\Entity\User;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class TweetService
{
    private const CACHE_TAG = 'tweets';

    private EntityManagerInterface $entityManager;
    
    private TagAwareCacheInterface $cache;

    public function __construct(EntityManagerInterface $entityManager, TagAwareCacheInterface $cache)
    {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }

    /**
     * @return Tweet[]
     *
     * @throws InvalidArgumentException
     */
    public function getTweets(int $page, int $perPage): array
    {
        /** @var TweetRepository $tweetRepository */
        $tweetRepository = $this->entityManager->getRepository(Tweet::class);

        /** @var ItemInterface $organizationsItem */
        return $this->cache->get(
            "tweets_{$page}_{$perPage}",
            function(ItemInterface $item) use ($tweetRepository, $page, $perPage) {
                $tweets = $tweetRepository->getTweets($page, $perPage);
                $tweetsSerialized = array_map(static fn(Tweet $tweet) => $tweet->toArray(), $tweets);
                $item->set($tweetsSerialized);
                $item->tag(self::CACHE_TAG);

                return $tweetsSerialized;
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function saveTweet(int $authorId, string $text): bool {
        $tweet = new Tweet();
        $userRepository = $this->entityManager->getRepository(User::class);
        $author = $userRepository->find($authorId);
        if (!($author instanceof User)) {
            return false;
        }
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $this->entityManager->persist($tweet);
        $this->entityManager->flush();

        $this->cache->invalidateTags([self::CACHE_TAG]);

        return true;
    }
}