<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
use MauticPlugin\LenonLeiteManyChatBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigBeforeSave', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'LenonLeiteManyChatBundle',
            'formType'   => ConfigType::class,
            'formAlias'  => 'manychatconfig',
            'parameters' => $event->getParametersFromConfig('LenonLeiteManyChatBundle'),
        ]);
    }

    public function onConfigBeforeSave(ConfigEvent $event): void
    {
        $values = $event->getConfig('manychatconfig');
        $event->setConfig($values, 'manychatconfig');
    }
}
