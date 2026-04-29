<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteManyChatBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LenonLeiteManyChatBundle\Integration\LenonLeiteManyChatIntegration;

class ConfigSupport extends LenonLeiteManyChatIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
