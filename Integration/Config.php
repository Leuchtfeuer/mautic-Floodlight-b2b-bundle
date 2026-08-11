<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;

class Config
{
    public function __construct(
        private readonly IntegrationsHelper $integrationsHelper,
    ) {
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
            $apiKeys     = $integration->getApiKeys();

            return is_array($apiKeys) ? $apiKeys : [];
        } catch (IntegrationNotFoundException) {
            return [];
        }
    }

    public function isConfigured(): bool
    {
        try {
            $apiKeys = $this->getApiKeys();

            return $apiKeys !== [] && isset($apiKeys['api_access_key_id']) && isset($apiKeys['api_secret_key']);
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * Checks if strict GDPR mode is enabled.
     */
    public function isStrictGdprMode(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            return is_array($featureSettings)
                   && isset($featureSettings['integration'])
                   && is_array($featureSettings['integration'])
                   && isset($featureSettings['integration']['strict_gdpr'])
                   && (bool) $featureSettings['integration']['strict_gdpr'];
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    public function setLast429Timestamp(): void
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)) {
                $featureSettings = [];
            }

            if (!isset($featureSettings['integration']) || !is_array($featureSettings['integration'])) {
                $featureSettings['integration'] = [];
            }

            $featureSettings['integration']['last_429_timestamp'] = time();
            $integration->setFeatureSettings($featureSettings);

            $this->integrationsHelper->saveIntegrationConfiguration($integration);
        } catch (IntegrationNotFoundException) {
            // Ignore if integration not found
        }
    }

    public function checkIfLookupReturned429ResponseInLast60Minutes(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)
                || !isset($featureSettings['integration'])
                || !is_array($featureSettings['integration'])
                || !isset($featureSettings['integration']['last_429_timestamp'])) {
                return false;
            }

            $last429timestamp          = (int) $featureSettings['integration']['last_429_timestamp'];
            $currentTime               = time();
            $sixtyMinutesInSeconds     = 60 * 60;

            return ($currentTime - $last429timestamp) < $sixtyMinutesInSeconds;
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    public function getIgnoreSingleClickSettings(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)
                || !isset($featureSettings['integration'])
                || !is_array($featureSettings['integration'])
                || !isset($featureSettings['integration']['ignore_single_click_visits'])) {
                return true;
            }

            return (bool) $featureSettings['integration']['ignore_single_click_visits'];
        } catch (IntegrationNotFoundException) {
            return true;
        }
    }

    public function getIgnoreTelcosSettings(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)
                || !isset($featureSettings['integration'])
                || !is_array($featureSettings['integration'])
                || !isset($featureSettings['integration']['ignore_telcos'])) {
                return true;
            }

            return (bool) $featureSettings['integration']['ignore_telcos'];
        } catch (IntegrationNotFoundException) {
            return true;
        }
    }
}
