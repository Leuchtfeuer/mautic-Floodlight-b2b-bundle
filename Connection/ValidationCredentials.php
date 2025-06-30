<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

class ValidationCredentials extends Credentials
{
    private string $accessKeyId = '';
    private string $secretKey   = '';

    // Own constructor so that integrations helper is not required
    public function __construct()
    {
    }

    public function getKeyName(): string
    {
        return 'Authorization';
    }

    public function getApiKey(): string
    {
        if (!empty($this->accessKeyId) && !empty($this->secretKey)) {
            return 'Basic '.base64_encode($this->accessKeyId.':'.$this->secretKey);
        }

        return '';
    }

    public function setAccessKeyId(string $accessKeyId): void
    {
        $this->accessKeyId = $accessKeyId;
    }

    public function setApiSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    // Aliase für die Form-Felder-Namen
    public function setApiAccessKeyId(string $accessKeyId): void
    {
        $this->setAccessKeyId($accessKeyId);
    }
}
