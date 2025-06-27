<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;

class Config
{
    public function __construct(private IntegrationsHelper $integrationsHelper)
    {
    }

    public function isPublished(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();
            return (bool) $integration->getIsPublished();
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        return $this->integrationsHelper->getIntegration(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME)->getIntegrationConfiguration();
    }

    /**
     * @return array<string, string>
     *
     * @throws IntegrationNotFoundException
     */
    public function getApiKeys(): array
    {
        try {
            $integration = $this->getIntegrationEntity();
            return $integration->getApiKeys() ?: [];
        } catch (IntegrationNotFoundException) {
            return [];
        }
    }

    public function isConfigured(): bool
    {
        try {
            $apiKeys = $this->getApiKeys();
            return !empty($apiKeys) && isset($apiKeys['api_access_key_id']) && isset($apiKeys['api_secret_key']);
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }
    
    /**
     * Checks if strict GDPR mode is enabled
     * 
     * @return bool
     */
    public function isStrictGdprMode(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();
            
            return !empty($featureSettings['integration']['strict_gdpr']) && (bool) $featureSettings['integration']['strict_gdpr'];
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    public function setLast429ErrorTimestamp(): void
    {
        try {
            $integration = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();
            
            if (!isset($featureSettings['integration'])) {
                $featureSettings['integration'] = [];
            }
            
            $featureSettings['integration']['last_429_error'] = time();
            $integration->setFeatureSettings($featureSettings);
            
            $this->integrationsHelper->saveIntegrationConfiguration($integration);
        } catch (IntegrationNotFoundException) {
            // Ignore if integration not found
        }
    }


    public function checkIfLookupReturned429ResponseInLast60Minutes(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();
            
            if (!isset($featureSettings['integration']['last_429_error'])) {
                return false;
            }
            
            $last429Error = (int) $featureSettings['integration']['last_429_error'];
            $currentTime = time();
            $sixtyMinutesInSeconds = 60 * 60;
            
            return ($currentTime - $last429Error) < $sixtyMinutesInSeconds;
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }
}
