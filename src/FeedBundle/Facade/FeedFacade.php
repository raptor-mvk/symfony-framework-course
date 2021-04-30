<?php

namespace FeedBundle\Facade;

use FeedBundle\Service\FeedService;

class FeedFacade
{
    private FeedService $feedService;

    /**
     * FeedFacade constructor.
     */
    public function __construct(FeedService $feedService)
    {
        $this->feedService = $feedService;
    }

    public function getFeed(int $userId, int $count): array
    {
        return $this->feedService->getFeed($userId, $count);
    }
}
