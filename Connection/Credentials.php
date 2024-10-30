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

    public function getApiKey(): string
    {
        return 'Bearer f:eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjMsImZyZWUiOnRydWUsImlhdCI6MTcyOTE1MjU3NH0.rJw4ut4wEVxkhJdT7tNrHRSGWrxHheoVj_HtuA0YbNM';
    }
}