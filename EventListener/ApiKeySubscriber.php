<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\IntegrationsBundle\Event\KeysSaveEvent;
use Mautic\IntegrationsBundle\IntegrationEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApiKeySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            IntegrationEvents::INTEGRATION_API_KEYS_BEFORE_SAVE => 'onApiKeysBeforeSave',
        ];
    }

    /**
     * This method is called before the API keys are saved.
     * It checks if the integration is Leuchtfeuer Floodlight B2B and if the API secret key field in the plugin config is empty.
     * If it is, it keeps the old API key.
     */
    public function onApiKeysBeforeSave(KeysSaveEvent $event): void
    {
        $integration = $event->getIntegrationConfiguration();

        if (LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME !== $integration->getName()) {
            return;
        }

        $newKeys = $event->getNewKeys();
        $oldKeys = $event->getOldKeys();

        if (empty($newKeys['api_secret_key']) && !empty($oldKeys['api_secret_key'])) {
            $newKeys = $oldKeys;
            $integration->setApiKeys($newKeys);
        }
    }
}
