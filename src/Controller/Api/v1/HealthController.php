<?php

namespace App\Controller\Api\v1;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Annotations\Route("/api/v1/health")
 */
class HealthController extends AbstractFOSRestController
{
    /**
     * @Annotations\Get("/check")
     */
    public function checkAction(): Response
    {
        return new JsonResponse(['success' => true]);
    }
}
