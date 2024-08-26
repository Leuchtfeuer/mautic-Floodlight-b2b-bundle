<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\InstallHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class PluginInstallSubscriber implements EventSubscriberInterface
{

    public const FIELD_DATA = [
        'alias' => 'iplookup_whois_json',
        'name'  => 'IP Lookup Whois',
        'type'  => 'textarea',
    ];

    public function __construct(private readonly InstallHelper $installHelper)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0]
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME)) {
            return;
        }
        $this->installHelper->copyBlacklistFile();
        $this->installHelper->installWhoisPythonLib();
        $this->installHelper->installFields(
            self::FIELD_DATA['alias'],
            self::FIELD_DATA['name'],
            self::FIELD_DATA['type']
        );
    }
}