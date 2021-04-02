<?php

namespace App\Controller\Api\SaveTweet\v1;

use App\Controller\Common\ErrorResponseTrait;
use App\Service\FeedService;
use App\Service\TweetService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Response;

class Controller
{
    use ControllerTrait, ErrorResponseTrait;

    private TweetService $tweetService;

    private FeedService $feedService;

    public function __construct(TweetService $tweetService, FeedService $feedService, ViewHandlerInterface $viewHandler)
    {
        $this->tweetService = $tweetService;
        $this->feedService = $feedService;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Post("/api/v1/tweet")
     *
     * @RequestParam(name="authorId", requirements="\d+")
     * @RequestParam(name="text")
     * @RequestParam(name="async", requirements="0|1", nullable=true)
     */
    public function saveTweetAction(int $authorId, string $text, ?int $async): Response
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

        return $this->handleView($this->view(['success' => $success], $code));
    }
}
