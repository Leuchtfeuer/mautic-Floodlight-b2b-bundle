<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Type\FloodlightB2BFeatureSettingsType;

class LeuchtfeuerFloodlightB2BIntegration extends BasicIntegration implements BasicInterface, ConfigFormFeatureSettingsInterface
{
    use ConfigurationTrait;

    public const INTEGRATION_NAME = 'LeuchtfeuerFloodlightB2B';
    public const DISPLAY_NAME     = 'Floodlight for B2B by Leuchtfeuer';

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
        return 'plugins/LeuchtfeuerFloodlightB2BBundle/Assets/img/icon.png';
    }

    public function getFeatureSettingsConfigFormName(): string
    {
        return FloodlightB2BFeatureSettingsType::class;
    }
}
