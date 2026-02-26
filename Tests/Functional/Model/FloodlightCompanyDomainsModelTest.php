<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Facade\ReloadFacade;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class FloodlightCompanyDomainsModelTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
    }

    public function testSuccessCheckIsNewIp(): void
    {
        $company = new Company();
        $company->setName('Test Company');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());
        $this->em->persist($company);
        $this->em->flush();
        $company = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company']);
        $this->assertInstanceOf(Company::class, $company);

        $domain = 'leuchtfeuer.com';
        $date   = new \DateTime();
        $entity = new FloodlightCompanyDomains();
        $entity->setDomain($domain);
        $entity->setDateAdded($date);
        $entity->setDateModified($date);
        $entity->setIsPrimaryDomain(true);
        $entity->setCompany($company);
        $this->em->persist($entity);
        $this->em->flush();
        $isNewIpFalse = $this->em->getRepository(FloodlightCompanyDomains::class)->isNew($domain);
        $isNewIpTrue  = $this->em->getRepository(FloodlightCompanyDomains::class)->isNew('www.mautic.com');
        $this->assertFalse($isNewIpFalse);
        $this->assertTrue($isNewIpTrue);
    }

    // Disabled: getCompanyDomains method was removed
    public function disabledtestGetCompanyDomainsWithLeadNull(): void
    {
        $this->markTestSkipped('getCompanyDomains method was removed');
    }

    // Disabled: getCompanyDomains method was removed
    public function disabledtestGetCompanyDomainsByFloodLight(): void
    {
        $this->markTestSkipped('getCompanyDomains method was removed');
    }

    // Disabled: getCompanyDomains method was removed
    public function disabledtestGetCompanyDomainsByGetHostByAddres(): void
    {
        $this->markTestSkipped('getCompanyDomains method was removed');
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $reloadFacade = self::getContainer()->get(ReloadFacade::class);
        $reloadFacade->reloadPlugins();
        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom  = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME]);

        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerFloodlightB2BIntegration']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerFloodlightB2B');
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom  = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (file_exists($configPathTo)) {
            unlink($configPathTo);
        }
        copy($pluginPathFrom, $configPathTo);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }
}
