<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use GuzzleHttp\ClientInterface;
use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory;
use Mautic\IntegrationsBundle\Exception\PluginNotConfiguredException;

class ApiClient
{
    public function __construct(
        private Credentials $credentials,
    ) {
    }

    /**
     * @throws PluginNotConfiguredException
     */
    public function getClient(): ClientInterface
    {
        return (new HttpFactory())->getClient($this->credentials);
    }

    public function setCredentials(Credentials $credentials): void
    {
        $this->credentials = $credentials;
    }
}
