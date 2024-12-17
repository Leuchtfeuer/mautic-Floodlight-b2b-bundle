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
//        return 'Bearer f:eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjMyLCJmcmVlIjp0cnVlLCJpYXQiOjE3MzA1NDM0Mzh9.lnKb5-Pt9ey5LEk4HGWCCqSQY0_C7AshKfwTVeDXsZQ';
                return '';
    }
}
