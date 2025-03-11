<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Facade\ReloadFacade;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;

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

    public function testGetCompanyDomainsWithLeadNull(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains');
        assert($floodlightLeadDomainsModel instanceof FloodlightCompanyDomainsModel);
        $result = $floodlightLeadDomainsModel->getCompanyDomains(null, []);
        $this->assertFalse($result);
    }

    public function testGetCompanyDomainsByFloodLight(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains');
        assert($floodlightLeadDomainsModel instanceof FloodlightCompanyDomainsModel);
        $fileContent   = file_get_contents(__DIR__.'/../../fixtures/floodlight.json');
        $this->assertIsString($fileContent);
        $dataJson      = json_decode($fileContent, true);
        $this->assertIsArray($dataJson);
        $floodlightDTO = new FloodlightDTO($dataJson);
        $domain        = 'le-non.de';
        $dataDomain    = [
            'domain'  => $domain,
            'status'  => 'valid',
            'details' => [
                'floodlight'        => $floodlightDTO,
                'statusRequestCode' => 200,
                'companyDomains'    => [new FloodlightCompanyDomains()],
            ],
        ];
        $lead          = $this->addLead();
        $companyDomain = $this->em->getRepository(FloodlightCompanyDomains::class)->findOneBy(['domain' => $domain]);
        $this->assertNull($companyDomain);
        $result        = $floodlightLeadDomainsModel->getCompanyDomains($lead, $dataDomain);
        $companyDomain = $this->em->getRepository(FloodlightCompanyDomains::class)->findOneBy(['domain' => $domain]);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomain);
        $this->assertSame($domain, $companyDomain->getDomain());
    }

    public function testgetCompanyDomainsByGetHostByAddres(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains');
        assert($floodlightLeadDomainsModel instanceof FloodlightCompanyDomainsModel);
        $domain = 'leuchtfeuer.com';

        $dataDomain = [
            'domain'  => $domain,
            'status'  => 'valid',
            'details' => [
                'hostname' => $domain,
            ],
        ];

        $lead          = $this->addLead();
        $companyDomain = $this->em->getRepository(FloodlightCompanyDomains::class)->findOneBy(['domain' => $domain]);
        $this->assertNull($companyDomain);
        $result = $floodlightLeadDomainsModel->getCompanyDomains($lead, $dataDomain);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
        $this->assertCount(1, $result['data']);
        $companyDomain = $this->em->getRepository(FloodlightCompanyDomains::class)->findOneBy(['domain' => $domain]);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomain);
        $this->assertSame($domain, $companyDomain->getDomain());
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

    private function addLead(): Lead
    {
        $rand = random_int(1, 10000000000);
        $lead = new Lead();
        $lead->setEmail($rand.'@example.com');
        $lead->setFirstname('John');
        $lead->setLastname('Doe');
        $lead->setDateAdded(new \DateTime());
        $lead->setDateIdentified(new \DateTime());
        $lead->setLastActive(new \DateTime());
        $lead->setPoints(0);
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
