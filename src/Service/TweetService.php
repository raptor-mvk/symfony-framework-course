<?php

namespace App\Service;

use App\Entity\Tweet;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

class TweetService
{
    private EntityManagerInterface $entityManager;
    
    private CacheItemPoolInterface $cacheItemPool;

    public function __construct(EntityManagerInterface $entityManager, CacheItemPoolInterface $cacheItemPool)
    {
        $this->entityManager = $entityManager;
        $this->cacheItemPool = $cacheItemPool;
    }

    /**
     * @return Tweet[]
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getTweets(int $page, int $perPage): array
    {
        /** @var TweetRepository $tweetRepository */
        $tweetRepository = $this->entityManager->getRepository(Tweet::class);

        $tweetsItem = $this->cacheItemPool->getItem("tweets_{$page}_{$perPage}");
        if (!$tweetsItem->isHit()) {
            $tweets = $tweetRepository->getTweets($page, $perPage);
            $tweetsItem->set(array_map(static fn(Tweet $tweet) => $tweet->toArray(), $tweets));
            $this->cacheItemPool->save($tweetsItem);
        }

        return $tweetsItem->get();
    }
}