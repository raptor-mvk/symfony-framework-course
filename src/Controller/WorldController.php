<?php

namespace App\Controller;

use App\Service\FormatService;
use App\Service\MessageService;
use Symfony\Component\HttpFoundation\Response;

class WorldController
{
    private FormatService $formatService;
    private MessageService $messageService;

    public function __construct(FormatService $formatService, MessageService $messageService)
    {
        $this->formatService = $formatService;
        $this->messageService = $messageService;
    }

    public function hello(): Response
    {
        $result = $this->formatService->format($this->messageService->printMessages('world'));
        return new Response("<html><body>$result</body></html>");
    }
}