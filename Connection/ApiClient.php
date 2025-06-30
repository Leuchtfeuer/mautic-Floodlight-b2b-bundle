<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use GuzzleHttp\ClientInterface;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\Credentials\HeaderCredentialsInterface;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;

class ApiClient
{
    public function __construct(
        private Credentials $credentials
    ) {
    }

    /**
     * @throws PluginNotConfiguredException
     */
    private function getCredentials(): HeaderCredentialsInterface
    {
        return $this->credentials;
    }

    /**
     * @throws PluginNotConfiguredException
     */
    public function getClient(): ClientInterface
    {
        return (new HttpFactory())->getClient($this->getCredentials());
    }

    public function setCredentials(Credentials $credentials): void
    {
        $this->credentials = $credentials;
    }
}
