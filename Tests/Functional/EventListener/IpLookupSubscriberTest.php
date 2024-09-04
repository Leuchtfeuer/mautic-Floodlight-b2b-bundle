<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Entity\Page;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightIpDomainLookups;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class IpLookupSubscriberTest extends MauticMysqlTestCase
{
    private $pageRepo;
    private $hitRepo;
    private $leadRepo;
    private $leadDomainRepo;
    private $companyRepo;
    private $companyDomainRepo;
    private $companyLeadRepo;

    public function setUp(): void
    {
        parent::setUp();

        $this->pageRepo          = $this->em->getRepository(Page::class);
        $this->hitRepo           = $this->em->getRepository(Hit::class);
        $this->leadRepo          = $this->em->getRepository(Lead::class);
        $this->leadDomainRepo    = $this->em->getRepository(FloodlightLeadDomains::class);
        $this->companyRepo       = $this->em->getRepository(Company::class);
        $this->companyDomainRepo = $this->em->getRepository(FloodlightCompanyDomains::class);
        $this->companyLeadRepo   = $this->em->getRepository(CompanyLead::class);
        $this->activePlugin();
        $this->updateApikey();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    private function updateApikey()
    {
        $crawler = $this->client->request('GET', '/s/plugins/config/LeuchtfeuerFloodlightB2B');
        $form    = $crawler->selectButton('Save')->form();
        $form['integration_config[apiKeys][api_key]']->setValue('pro_12334');
        $crawler = $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    public function testPluginNoActivated(): void
    {
        $this->activePlugin(false);
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $crawler = $this->client->request('GET', '/s/plugins/reload');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/blacklist.php';
        $pluginPathFrom  = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/Config/blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME]);

        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerFloodlightB2BBundle']);
            $integration = new Integration();
            $integration->setName(LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME);
            $integration->setPlugin($plugin);
        }
        //        $apiKeys = $integration->getApiKeys();
        //        $apiKeys['api_key'] = '21313213';
        //        $integration->setApiKeys($apiKeys);
        $integration->setApiKeys([]);
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
    }

    public function testIpLookupWithOnlyOneLeadFail(): void
    {
        $leads = $this->leadRepo->findAll();
        $this->assertEquals(0, count($leads));
        $this->client->request('GET', '/mtracking.gif');
        $leads = $this->leadRepo->findAll();
        $this->assertEquals(1, count($leads));
        $leadDomains = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($leadDomains));
    }

    public function testIplookupFirstAddSubscriberValidLeadDomainAndNewCompany(): void
    {
        $totalItemsBeforeRequest        = $this->leadDomainRepo->findAll();
        $totalCompaniesBeforeRequest    = $this->companyRepo->findAll();
        $totalCompaniesDomainBeforeRepo = $this->companyDomainRepo->findAll();
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $this->assertEquals(0, count($totalItemsBeforeRequest));
        $this->assertEquals(0, count($totalCompaniesBeforeRequest));
        $this->assertEquals(0, count($totalCompaniesDomainBeforeRepo));
        $totalItemsAfterRequest        = $this->leadDomainRepo->findAll();
        $totalCompaniesAfterRequest    = $this->companyRepo->findAll();
        $totalCompaniesDomainAfterRepo = $this->companyDomainRepo->findAll();
        $this->assertEquals(1, count($totalItemsAfterRequest));
        $this->assertEquals('localhost', $totalItemsAfterRequest[0]->getDomain());
        $this->assertEquals('localhost', $totalCompaniesAfterRequest[0]->getName());
        $this->assertEquals('localhost', $totalCompaniesDomainAfterRepo[0]->getDomain());
    }

    public function testIplookupLessThanOneHourFail(): void
    {
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(1, count($items));
        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(1, count($items));
    }

    public function testIpLookupPlusTwoHoursSuccessAddCount(): void
    {
        $items = $this->leadDomainRepo->findAll();
        $this->leadDomainRepo->deleteEntities($items);

        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($items));

        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($items));

        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(1, count($items));

        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(1, count($items));

        $lastItem = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);

        $lead = $this->createLead();
        $lastItem->setDateModified(new \DateTime('-2 hours'));
        $lastItem->setLead($lead);
        $this->em->persist($lastItem);
        $this->em->flush();

        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(2, count($items));

        $lastItem = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);
        $lead     = $this->createLead();
        $lastItem->setDateModified(new \DateTime('-2 hours'));
        $lastItem->setLead($lead);
        $this->em->persist($lastItem);
        $this->em->flush();

        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(3, count($items));
    }

    public function testLookupCreateCompany(): void
    {
        $companies = $this->em->getRepository(Company::class)->findAll();
        $this->assertEquals(0, count($companies));
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEquals(0, count($companyDomains));
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $lastItem = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);
        $this->assertEquals(1, $lastItem->getCount());
        $this->assertEquals('localhost', $lastItem->getDomain());
        $companies = $this->em->getRepository(Company::class)->findAll();
        $this->assertEquals(1, count($companies));
        $this->assertEquals('localhost', $companies[0]->getName());
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEquals(1, count($companyDomains));
        $this->assertEquals('localhost', $companyDomains[0]->getDomain());
    }

    public function testIpLookUpBlacklisted(): void
    {
        $totalItemsBeforeRequest        = $this->leadDomainRepo->findAll();
        $totalCompaniesBeforeRequest    = $this->companyRepo->findAll();
        $totalCompaniesDomainBeforeRepo = $this->companyDomainRepo->findAll();
        $this->assertEmpty($totalItemsBeforeRequest);
        $this->assertEmpty($totalCompaniesBeforeRequest);
        $this->assertEmpty($totalCompaniesDomainBeforeRepo);
        $blacklistHelper = new DomainBlacklistHelper();
        $blacklistHelper->add('localhost');
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $lastItem          = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);
        $lastCompany       = $this->companyRepo->findOneBy([], ['id' => 'DESC']);
        $lastCompanyDomain = $this->companyDomainRepo->findOneBy([], ['id' => 'DESC']);
        $this->assertEquals(1, $lastItem->getCount());
        $this->assertEquals('blacklisted', $lastItem->getDomain());
        $this->assertEmpty($lastCompany);
        $this->assertEmpty($lastCompanyDomain);
        $blacklistHelper->remove('localhost');
    }

    public function testIpLookUpBlacklistedCheckCompanyLead(): void
    {
        $totalItemsBeforeRequest        = $this->leadDomainRepo->findAll();
        $totalCompaniesDomainBeforeRepo = $this->companyDomainRepo->findAll();
        $this->assertEmpty($totalItemsBeforeRequest);
        $this->assertEmpty($totalCompaniesDomainBeforeRepo);
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $lastItem          = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);
        $test              = $this->companyRepo->findAll();
        $domain            = $lastItem->getDomain();
        $lastCompany       = $this->companyDomainRepo->findOneBy(['domain' => $domain]);
        $this->assertEquals('localhost', $lastCompany->getDomain());
        $companyLead = $this->companyLeadRepo->getCompanyLeadEntity($lastItem->getLead()->getId(), $lastCompany->getCompany()->getId());
        $this->assertNotEmpty($companyLead);
        $this->assertEquals($lastItem->getLead()->getId(), $companyLead[0]['lead_id']);
    }

    public function testIfTableIsInstalled()
    {
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $ipDomains = $this->em->getRepository(FloodlightIpDomainLookups::class)->getTableColumns();
        $this->assertNotEmpty($ipDomains);
        $this->assertContains('id', $ipDomains);
        $this->assertContains('ip_address', $ipDomains);
        $this->assertContains('date_added', $ipDomains);
        $this->assertContains('type', $ipDomains);
    }

    public function testIfTableIsInstalledCompanyDomains()
    {
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->getTableColumns();
        $this->assertNotEmpty($companyDomains);
        $this->assertContains('id', $companyDomains);
        $this->assertContains('domain', $companyDomains);
        $this->assertContains('date_added', $companyDomains);
        $this->assertContains('date_modified', $companyDomains);
    }

    private function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setFirstname('Contact');
        $lead->setEmail('test@test.com');
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
