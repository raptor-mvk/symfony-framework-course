<?php
// src/EventListener/RequestListener.php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RequestListener
{
    public function onKernelRequest(ResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            // не делать ничего, если это не запрос главного абонента
            return;
        }

        // ...
    }
}