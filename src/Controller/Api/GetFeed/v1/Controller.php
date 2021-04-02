<?php

namespace App\Controller\Api\GetFeed\v1;

use App\Service\FeedService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;

final class Controller
{
    /** @var int */
    private const DEFAULT_FEED_SIZE = 20;

    private FeedService $feedService;

    public function __construct(FeedService $feedService)
    {
        $this->feedService = $feedService;
    }

    /**
     * @Rest\Get("/api/v1/get-feed")
     *
     * @Rest\QueryParam(name="userId", requirements="\d+")
     * @Rest\QueryParam(name="count", requirements="\d+", nullable=true)
     */
    public function getFeedAction(int $userId, ?int $count = null): View
    {
        $count = $count ?? self::DEFAULT_FEED_SIZE;
        $tweets = $this->feedService->getFeed($userId, $count);
        $code = empty($tweets) ? 204 : 200;

        return View::create(['tweets' => $tweets], $code);
    }
}
