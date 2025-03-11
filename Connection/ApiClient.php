<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use GuzzleHttp\ClientInterface;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory;
use Mautic\IntegrationsBundle\Auth\Provider\AuthCredentialsInterface;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;

class ApiClient
{
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
        return (new HttpFactory())->getClient($this->getCredentials());
    }
}
