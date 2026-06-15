<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations\Version_5_8_0;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CompanyFieldValueSanitizer;

class LeuchtfeuerFloodlightB2BBundle extends AbstractPluginBundle
{
    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, mixed $metadata = null, mixed $installedSchema = null): void
    {
        self::onPluginUpdate($plugin, $factory, is_array($metadata) ? $metadata : null, $installedSchema instanceof Schema ? $installedSchema : null);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public static function onPluginUpdate(Plugin $plugin, MauticFactory $factory, $metadata = null, ?Schema $installedSchema = null): void
    {
        /** @var CompanyFieldValueSanitizer $sanitizer */
        $sanitizer = $factory->get(CompanyFieldValueSanitizer::class);
        Version_5_8_0::setSanitizer($sanitizer);

        parent::onPluginUpdate($plugin, $factory, $metadata, $installedSchema);
    }
}
