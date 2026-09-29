<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final readonly class FixtureHelper
{
    public function __construct(
        private EntityManagerInterface $em,
        private KernelBrowser $client,
    ) {
    }

    /**
     * Registers the plugin, creates all f4b_* custom DB columns via the install subscriber,
     * and saves API keys through the config form (which handles Mautic's key encryption).
     *
     * Use null for $accessKeyId / $secretKey to leave the field empty in the form (simulates "no API key set").
     */
    public function createAndEnablePlugin(
        bool $isPublished = true,
        ?string $accessKeyId = 'validAccessKeyId',
        ?string $secretKey = 'validSecretKey',
    ): void {
        // HTTP request triggers PluginInstallSubscriber → creates f4b_* columns in the companies table.
        // reloadFacade::reloadPlugins() alone does NOT create DB columns.
        $this->client->request('GET', '/s/plugins/reload');

        $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerFloodlightB2BBundle']);
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME])
            ?? new Integration();

        $integration->setName(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME);
        $integration->setPlugin($plugin);
        $integration->setIsPublished($isPublished);
        $integration->setApiKeys([]);
        $this->em->persist($integration);
        $this->em->flush();

        // Submit the config form so Mautic encrypts and persists the API keys correctly.
        $crawler = $this->client->request('GET', '/s/plugins/config/LeuchtfeuerFloodlightB2B');
        $form    = $crawler->selectButton('Save')->form();
        if (null !== $accessKeyId) {
            $form['integration_config[apiKeys][api_access_key_id]']->setValue($accessKeyId);
        }
        if (null !== $secretKey) {
            $form['integration_config[apiKeys][api_secret_key]']->setValue($secretKey);
        }
        $this->client->submit($form);
    }

    public function copyBlacklistConfig(): void
    {
        $pathsHelper    = $this->client->getContainer()->get(PathsHelper::class);
        $configPathTo   = $pathsHelper->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom = $pathsHelper->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }
    }
}
