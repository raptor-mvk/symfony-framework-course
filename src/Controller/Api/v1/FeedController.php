<?php
declare(strict_types=1);

namespace App\Controller\Api\v1;

use App\Service\FeedService;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\View\View;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Annotations\Route("/api/v1/feed")
 */
final class FeedController
{
    /** @var int */
    private const DEFAULT_FEED_SIZE = 20;

    /** @var FeedService */
    private $feedService;

    public function __construct(FeedService $feedService)
    {
        $this->feedService = $feedService;
    }

    /**
     * @Annotations\Get("")
     *
     * @Annotations\QueryParam(name="userId", requirements="\d+")
     * @Annotations\QueryParam(name="count", requirements="\d+", nullable=true)
     */
    public function getFeedAction(int $userId, ?int $count = null): View
    {
        $count = $count ?? self::DEFAULT_FEED_SIZE;
        $tweets = $this->feedService->getFeed($userId, $count);
        $code = empty($tweets) ? 204 : 200;

        return View::create(['tweets' => $tweets], $code);
    }
}
