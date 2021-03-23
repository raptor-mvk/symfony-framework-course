<?php

namespace App\Controller\Api\SaveTweet\v1;

use App\Controller\Common\ErrorResponseTrait;
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

    public function __construct(TweetService $tweetService, ViewHandlerInterface $viewHandler)
    {
        $this->tweetService = $tweetService;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Post("/api/v1/tweet")
     *
     * @RequestParam(name="authorId", requirements="\d+")
     * @RequestParam(name="text")
     */
    public function saveUserAction(int $authorId, string $text): Response
    {
        $tweetId = $this->tweetService->saveTweet($authorId, $text);
        [$data, $code] = ($tweetId === null) ? [['success' => false], 400] : [['tweet' => $tweetId], 200];
        return $this->handleView($this->view($data, $code));
    }
}
