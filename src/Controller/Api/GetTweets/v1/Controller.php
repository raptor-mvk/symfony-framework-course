<?php

namespace App\Controller\Api\GetTweets\v1;

use App\Entity\Tweet;
use App\Service\TweetService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller
{
    use ControllerTrait;

    private TweetService $tweetService;

    public function __construct(TweetService $tweetService, ViewHandlerInterface $viewHandler)
    {
        $this->tweetService = $tweetService;
        $this->viewhandler = $viewHandler;
    }

    /**
     * @Rest\Get("/api/v1/tweet")
     */
    public function getTweetsAction(Request $request): Response
    {
        $perPage = $request->query->get('perPage');
        $page = $request->query->get('page');
        $tweets = $this->tweetService->getTweets($page ?? 0, $perPage ?? 20);
        $code = empty($tweets) ? 204 : 200;
        $view = $this->view(['tweets' => $tweets], $code);

        return $this->handleView($view);
    }
}
