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
        } catch (IntegrationNotFoundException $e) {
            return false;
        }
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        $integrationObject = $this->integrationsHelper->getIntegration(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME);

        return $integrationObject->getIntegrationConfiguration();
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
        } catch (IntegrationNotFoundException $e) {
            return [];
        }
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function isConfigured(): bool
    {
        $apiKeys = $this->getApiKeys();

        return !empty($apiKeys['api_key']);
    }

    public function isFreeTier(): bool
    {
        $apiKeys = $this->getApiKeys();
        if (empty($apiKeys['api_key'])) {
            return false;
        }
        if (str_contains($apiKeys['api_key'], 'free_')) {
            return true;
        }

        return false;
    }

    public function isProTier(): bool
    {
        if (!$this->isConfigured() || $this->isFreeTier()) {
            return false;
        }

        if (str_contains($this->getApiKeys()['api_key'], 'pro_')) {
            return true;
        }

        return false;
    }
}
