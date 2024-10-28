<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\Credentials\HeaderCredentialsInterface;

class Credentials implements HeaderCredentialsInterface
{
    public function getKeyName(): string
    {
        return 'Authorization';
    }

    public function getApiKey(): ?string
    {
        return 'Bearer ';
    }
}
