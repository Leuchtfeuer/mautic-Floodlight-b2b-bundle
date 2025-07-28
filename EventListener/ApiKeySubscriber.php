<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\IntegrationsBundle\Event\ConfigSaveEvent;
use Mautic\IntegrationsBundle\IntegrationEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApiKeySubscriber implements EventSubscriberInterface
{
    public function __construct(private Config $config)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            IntegrationEvents::INTEGRATION_CONFIG_BEFORE_SAVE => 'onConfigBeforeSave',
        ];
    }

    public function onConfigBeforeSave(ConfigSaveEvent $event): void
    {
        $integration = $event->getIntegrationConfiguration();
        
        // Nur für unsere Integration
        if ($integration->getName() !== LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME) {
            return;
        }

        $currentKeys = $integration->getApiKeys(); // Keys aus dem Form
        $realOldSecretKey = $this->config->getCurrentSecretKey();

        // Wenn api_secret_key leer ist, echten alten Wert aus DB holen
        if (empty($currentKeys['api_secret_key'])) {
            $realOldSecretKey = $this->config->getCurrentSecretKey();
            
            if (!empty($realOldSecretKey)) {
                $currentKeys['api_secret_key'] = $realOldSecretKey;
                $integration->setApiKeys($currentKeys);
            }
        }
    }
}