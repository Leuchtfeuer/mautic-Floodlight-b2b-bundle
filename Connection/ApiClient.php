<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use GuzzleHttp\ClientInterface;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory;
use Mautic\IntegrationsBundle\Auth\Provider\AuthCredentialsInterface;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;

// use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\Credentials;

class ApiClient
{
    public function __construct(private HttpFactory $httpFactory)
    {
    }

    /**
     * @throws PluginNotConfiguredException
     */
    private function getCredentials(): AuthCredentialsInterface
    {
        return new Credentials();
    }

    /**
     * @throws PluginNotConfiguredException
     */
    public function getClient(): ClientInterface
    {
        return $this->httpFactory->getClient($this->getCredentials());
    }
}
