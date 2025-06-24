<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection;

use Mautic\IntegrationsBundle\Auth\Provider\ApiKey\Credentials\HeaderCredentialsInterface;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class ValidationCredentials extends Credentials
{
    private string $accessKeyId;
    private string $secretKey;
    
    public function getKeyName(): string
    {
        return 'Authorization';
    }

    public function getApiKey(): string
    {
        // Basic Auth mit username (Access Key ID) und password (Secret Key)
        if (!empty($this->accessKeyId) && !empty($this->secretKey)) {
            return 'Basic ' . base64_encode($this->accessKeyId . ':' . $this->secretKey);
        }
        
        return '';
    }

    public function setAccessKeyId(string $accessKeyId): void
    {
        $this->accessKeyId = $accessKeyId;
    }
    
    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }
    
    // Für Backward-Kompatibilität - kann entfernt werden wenn nicht mehr benötigt
    public function setApiKey(string $apiKey): void
    {
        // Versuche zu parsen falls das alte Format verwendet wird
        if (str_contains($apiKey, ':')) {
            $parts = explode(':', $apiKey, 2);
            $this->accessKeyId = $parts[0];
            $this->secretKey = $parts[1];
        }
    }
}
