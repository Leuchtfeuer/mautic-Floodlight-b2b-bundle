<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use GuzzleHttp\ClientInterface;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory;
use Mautic\IntegrationsBundle\Auth\Provider\AuthCredentialsInterface;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;

class ApiClient
{
    private ClientInterface $client;

    /**
     * @throws PluginNotConfiguredException
     */

    /**
     * @throws PluginNotConfiguredException
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function setClient(ClientInterface $client): void
    {
        $this->client = $client;
    }
}
