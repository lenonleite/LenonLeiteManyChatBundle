<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class LenonLeiteManyChatIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const INTEGRATION_NAME = 'lenonleitemanychat';
    public const DISPLAY_NAME     = 'ManyChat Connector';

    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/LenonLeiteManyChatBundle/Assets/img/icon.svg';
    }
}
