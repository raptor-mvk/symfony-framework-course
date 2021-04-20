<?php
declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\Entity\Feed;
use App\Service\FeedService;
use App\Service\SubscriptionService;
use App\Service\TweetService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\View\View;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Annotations\Route("/api/v1/tweet")
 */
final class TweetController extends AbstractFOSRestController
{
    /** @var int */
    private const DEFAULT_FEED_SIZE = 20;

    /** @var TweetService */
    private $tweetService;
    /** @var SubscriptionService */
    private $subscriptionService;
    /** @var FeedService */
    private $feedService;

    public function __construct(TweetService $tweetService, SubscriptionService $subscriptionService, FeedService $feedService)
    {
        $this->tweetService = $tweetService;
        $this->subscriptionService = $subscriptionService;
        $this->feedService = $feedService;
    }

    /**
     * @Annotations\Post("")
     *
     * @RequestParam(name="authorId", requirements="\d+")
     * @RequestParam(name="text")
     * @RequestParam(name="async", requirements="0|1", nullable=true)
     */
    public function postTweetAction(int $authorId, string $text, ?int $async): View
    {
        $tweet = $this->tweetService->saveTweet($authorId, $text);
        $success = $tweet !== null;
        if ($success) {
            if ($async === 1) {
                $this->feedService->spreadTweetAsync($tweet);
            } else {
                $this->feedService->spreadTweetSync($tweet);
            }
        }
        $code = $success ? 200 : 400;

        return View::create(['success' => $success], $code);
    }

    /**
     * @Annotations\Get("/feed")
     *
     * @QueryParam(name="userId", requirements="\d+")
     * @QueryParam(name="count", requirements="\d+", nullable=true)
     */
    public function getFeedAction(int $userId, ?int $count = null): View
    {
        $count = $count ?? self::DEFAULT_FEED_SIZE;
        $authorIds = $this->subscriptionService->getAuthorIds($userId);
        $tweets = $this->tweetService->getFeed($authorIds, $count);
        $code = empty($tweets) ? 204 : 200;

        return View::create(['tweets' => $tweets], $code);
    }
}