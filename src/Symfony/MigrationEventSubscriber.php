<?php

namespace App\Symfony;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class MigrationEventSubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return ['postGenerateSchema'];
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $schema = $args->getSchema();
        if (!$schema->hasNamespace('public')) {
            $schema->createNamespace('public');
        }
    }
}