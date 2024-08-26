<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\InstallHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext('Floodlight for B2B')) {
            return;
        }

        $this->installHelper->copyBlacklistFile();
        $this->installHelper->installWhoisPythonLib();
        //        $this->installHelper->installFields(
        //            self::FIELD_DATA['alias'],
        //            self::FIELD_DATA['name'],
        //            self::FIELD_DATA['type']
        //        );c
    }
}
