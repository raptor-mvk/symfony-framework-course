<?php

namespace App\Service;

use App\Entity\Tweet;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;

class TweetService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return Tweet[]
     */
    public function getTweets(int $page, int $perPage): array
    {
        /** @var TweetRepository $TweetRepository */
        $TweetRepository = $this->entityManager->getRepository(Tweet::class);

        return $TweetRepository->getTweets($page, $perPage);
    }
}