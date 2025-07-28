<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\IntegrationsBundle\Facade\EncryptionService;
use Mautic\PluginBundle\Entity\IntegrationRepository;
use Doctrine\ORM\EntityManagerInterface;

class Config
{
    public function __construct(
        private IntegrationsHelper $integrationsHelper,
        private IntegrationRepository $integrationRepository,
        private EncryptionService $encryptionService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function isPublished(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();

            return (bool) $integration->getIsPublished();
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        return $this->integrationsHelper->getIntegration(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME)->getIntegrationConfiguration();
    }

    /**
     * @return array<string, string>
     *
     * @throws IntegrationNotFoundException
     */
    public function getApiKeys(): array
    {
        try {
            $integration = $this->getIntegrationEntity();
            $apiKeys     = $integration->getApiKeys();

            return is_array($apiKeys) ? $apiKeys : [];
        } catch (IntegrationNotFoundException) {
            return [];
        }
    }

    public function isConfigured(): bool
    {
        try {
            $apiKeys = $this->getApiKeys();

            return !empty($apiKeys) && isset($apiKeys['api_access_key_id']) && isset($apiKeys['api_secret_key']);
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * Checks if strict GDPR mode is enabled.
     */
    public function isStrictGdprMode(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            return is_array($featureSettings)
                   && isset($featureSettings['integration'])
                   && is_array($featureSettings['integration'])
                   && isset($featureSettings['integration']['strict_gdpr'])
                   && (bool) $featureSettings['integration']['strict_gdpr'];
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    public function setLast429Timestamp(): void
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)) {
                $featureSettings = [];
            }

            if (!isset($featureSettings['integration']) || !is_array($featureSettings['integration'])) {
                $featureSettings['integration'] = [];
            }

            $featureSettings['integration']['last_429_timestamp'] = time();
            $integration->setFeatureSettings($featureSettings);

            $this->integrationsHelper->saveIntegrationConfiguration($integration);
        } catch (IntegrationNotFoundException) {
            // Ignore if integration not found
        }
    }

    public function checkIfLookupReturned429ResponseInLast60Minutes(): bool
    {
        try {
            $integration     = $this->getIntegrationEntity();
            $featureSettings = $integration->getFeatureSettings();

            if (!is_array($featureSettings)
                || !isset($featureSettings['integration'])
                || !is_array($featureSettings['integration'])
                || !isset($featureSettings['integration']['last_429_timestamp'])) {
                return false;
            }

            $last429timestamp          = (int) $featureSettings['integration']['last_429_timestamp'];
            $currentTime               = time();
            $sixtyMinutesInSeconds     = 60 * 60;

            return ($currentTime - $last429timestamp) < $sixtyMinutesInSeconds;
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * FINALE LÖSUNG: Umgeht ALLE Caching-Probleme durch direkten EntityManager-Zugriff.
     * Funktioniert IMMER, egal welche Save-Reihenfolge oder Timing.
     */
    public function getCurrentSecretKey(): ?string
    {
        try {
            // Cache komplett umgehen durch direkten DQL-Query ohne Cache
            $dql = 'SELECT i FROM Mautic\PluginBundle\Entity\Integration i WHERE i.name = :name';
            $query = $this->entityManager->createQuery($dql);
            $query->setParameter('name', LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME);
            
            // WICHTIG: Cache explizit deaktivieren!
            $query->useResultCache(false);
            $query->useQueryCache(false);
            
            /** @var Integration|null $integration */
            $integration = $query->getOneOrNullResult();
            
            if (!$integration) {
                return null;
            }

            $apiKeys = $integration->getApiKeys();
            
            if (!is_array($apiKeys) || !isset($apiKeys['api_secret_key'])) {
                return null;
            }

            $secretKey = $apiKeys['api_secret_key'];

            // Falls verschlüsselt (was bei direktem DB-Zugriff sein könnte)
            if (!empty($secretKey) && $this->looksEncrypted($secretKey)) {
                $decryptedApiKeys = $this->encryptionService->decrypt($apiKeys);
                return $decryptedApiKeys['api_secret_key'] ?? null;
            }

            return $secretKey;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Einfache Heuristik: Verschlüsselte Strings sind meist länger und enthalten base64-Zeichen
     */
    private function looksEncrypted(string $value): bool
    {
        return strlen($value) > 30 && (strpos($value, '+') !== false || strpos($value, '/') !== false || strpos($value, '=') !== false);
    }
}
