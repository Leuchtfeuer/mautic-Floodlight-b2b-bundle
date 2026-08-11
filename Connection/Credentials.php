<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\Credentials\HeaderCredentialsInterface;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class Credentials implements HeaderCredentialsInterface
{
    public function __construct(
        private readonly IntegrationsHelper $integrationsHelper,
    ) {
    }

    public function getKeyName(): string
    {
        return 'Authorization';
    }

    public function getApiKey(): string
    {
        $integration = $this->integrationsHelper->getIntegration(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME);
        $config      = $integration->getIntegrationConfiguration();
        $apiKeys     = $config->getApiKeys();

        if (!is_array($apiKeys)) {
            return '';
        }

        $accessKeyId = $apiKeys['api_access_key_id'] ?? '';
        $secretKey   = $apiKeys['api_secret_key'] ?? '';

        if (!empty($accessKeyId) && !empty($secretKey)) {
            return 'Basic '.base64_encode($accessKeyId.':'.$secretKey);
        }

        return '';
    }
}
