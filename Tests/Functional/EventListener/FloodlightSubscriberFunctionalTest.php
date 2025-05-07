<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Facade\ReloadFacade;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\FloodlightSubscriber;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class FloodlightSubscriberFunctionalTest extends MauticMysqlTestCase
{
    private LeadRepository $leadRepo;
    private FloodlightLeadDomainsRepository $leadDomainRepo;
    private CompanyRepository $companyRepo;
    private FloodlightCompanyDomainsRepository $companyDomainRepo;
    private CompanyLeadRepository $companyLeadRepo;

    public function setUp(): void
    {
        parent::setUp();

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

    private function updateApikey(string $key='asd13'): void
    {
        $crawler = $this->client->request('GET', '/s/plugins/config/LeuchtfeuerFloodlightB2B');
        $form    = $crawler->selectButton('Save')->form();
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\InputFormField::class, $form['integration_config[apiKeys][api_key]']);
        $form['integration_config[apiKeys][api_key]']->setValue($key);
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
        $reloadFacade = self::getContainer()->get(ReloadFacade::class);
        $reloadFacade->reloadPlugins();
        $this->client->request('GET', '/s/plugins/reload');
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
        //        $this->useCleanupRollback = false;
        //        $this->setUpSymfony($this->configParams);
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

        $this->assertEquals(0, count($totalItemsBeforeRequest));
        $this->assertEquals(0, count($totalCompaniesBeforeRequest));
        $this->assertEquals(0, count($totalCompaniesDomainBeforeRepo));

        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');

        $totalItemsAfterRequest        = $this->leadDomainRepo->findAll();
        $totalCompaniesAfterRequest    = $this->companyRepo->findAll();
        $totalCompaniesDomainAfterRepo = $this->companyDomainRepo->findAll();
        //        dd($totalItemsAfterRequest,$totalCompaniesAfterRequest,$totalCompaniesDomainAfterRepo);
        $this->assertEquals(1, count($totalItemsAfterRequest));
        assert($totalItemsAfterRequest[0] instanceof FloodlightLeadDomains);
        assert($totalCompaniesDomainAfterRepo[0] instanceof FloodlightCompanyDomains);
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

        assert($lastItem instanceof FloodlightLeadDomains);

        $lead = $this->createLead();
        $lastItem->setDateModified(new \DateTime('-2 hours'));
        $lastItem->setContact($lead);
        $this->em->persist($lastItem);
        $this->em->flush();

        $this->client->request('GET', '/mtracking.gif');
        $items = $this->leadDomainRepo->findAll();
        $this->assertEquals(2, count($items));

        $lastItem = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);
        assert($lastItem instanceof FloodlightLeadDomains);
        $lead     = $this->createLead();
        $lastItem->setDateModified(new \DateTime('-2 hours'));
        $lastItem->setContact($lead);
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
        assert($lastItem instanceof FloodlightLeadDomains);
        $this->assertEquals(1, $lastItem->getCount());
        $this->assertEquals('localhost', $lastItem->getDomain());
        $companies = $this->em->getRepository(Company::class)->findAll();
        $this->assertEquals(1, count($companies));
        $this->assertEquals('localhost', $companies[0]->getName());
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEquals(1, count($companyDomains));
        assert($companyDomains[0] instanceof FloodlightCompanyDomains);
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
        $lastCompanyDomain = $this->companyDomainRepo->findOneBy([], ['company' => 'ASC', 'domain' => 'ASC']);
        assert($lastItem instanceof FloodlightLeadDomains);
        $this->assertEquals(1, $lastItem->getCount());
        $this->assertEquals('blacklisted', $lastItem->getStatus());
        //Hier noch domain testen
        $this->assertEmpty($lastCompany);
        $this->assertEmpty($lastCompanyDomain);
        $blacklistHelper->remove('localhost');
    }

    public function testIpLookUpBlacklistedCheckCompanyLeadTwoTries(): void
    {
        $totalItemsBeforeRequest        = $this->leadDomainRepo->findAll();
        $totalCompaniesDomainBeforeRepo = $this->companyDomainRepo->findAll();
        $this->assertEmpty($totalItemsBeforeRequest);
        $this->assertEmpty($totalCompaniesDomainBeforeRepo);
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $lastItem          = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);

        assert($lastItem instanceof FloodlightLeadDomains);
        $test              = $this->companyRepo->findAll();
        $domain            = $lastItem->getDomain();

        $lastCompany       = $this->companyDomainRepo->findOneBy(['domain' => $domain]);
        assert($lastItem instanceof FloodlightLeadDomains);
        assert($lastCompany instanceof FloodlightCompanyDomains);
        $this->assertEquals('localhost', $lastCompany->getDomain());
        assert($lastCompany->getCompany() instanceof Company);
        assert($lastItem->getContact() instanceof Lead);
        $companyLead = $this->companyLeadRepo->getCompanyLeadEntity($lastItem->getContact()->getId(), $lastCompany->getCompany()->getId());
        $this->assertNotEmpty($companyLead);
        $this->assertIsArray($companyLead);
        $this->assertCount(1, $companyLead);
        $this->assertArrayHasKey('lead_id', $companyLead[0]);
        $this->assertEquals($lastItem->getContact()->getId(), $companyLead[0]['lead_id']);
    }

    public function testIfTableIsInstalled(): void
    {
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $ipLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->getTableColumns();
        $this->assertNotEmpty($ipLeadDomains);
        $this->assertContains('id', $ipLeadDomains);
        $this->assertContains('date_added', $ipLeadDomains);
        $this->assertContains('lookup_type', $ipLeadDomains);
    }

    public function testIfTableIsInstalledCompanyDomains(): void
    {
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->getTableColumns();
        $this->assertNotEmpty($companyDomains);
        $this->assertContains('domain', $companyDomains);
        $this->assertContains('date_added', $companyDomains);
        $this->assertContains('date_modified', $companyDomains);
    }

    public function testOnPageHitSaveFlowFiveTimes(): void
    {
        $floodlightSubscriber = self::getContainer()->get(FloodlightSubscriber::class);
        $this->updateApikey('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjMwLCJmcmVlIjpmYWxzZSwiaWF0IjoxNzQxMjY5MTM0fQ.X2hk5QvfRP2JhpgsaUO-Q_fQftKPEat9Ibr9ANBKw00');
        assert($floodlightSubscriber instanceof FloodlightSubscriber);

        $pageHitEventMock1 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);

        $lead = $this->createLead();
        $hit  = $this->createHit($lead, '217.91.35.16');

        $pageHitEventMock1->expects($this->exactly(1))->method('getHit')->willReturn($hit);

        $floodlightSubscriber->onPageHit($pageHitEventMock1);
        $company1           = $this->em->getRepository(Company::class)->findAll();
        $leadDomainsHit1    = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $companyDomainsHit1 = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEmpty($company1);
        $this->assertEmpty($leadDomainsHit1);
        $this->assertEmpty($companyDomainsHit1);

        // Hit 2 with the same lead, save lead domain and company domain
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->server->set('REMOTE_ADDR', '217.91.35.16');
        $hit2              = $this->createHit($lead, '217.91.35.16');
        $pageHitEventMock2 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock2->expects($this->exactly(2))->method('getHit')->willReturn($hit2);
        $pageHitEventMock2->expects($this->exactly(1))->method('getRequest')->willReturn($request);
        $floodlightSubscriber->onPageHit($pageHitEventMock2);
        $leadDomainsHit2    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit2[0]);
        $companyDomainsHit2 = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit2[0]->getDomain()]);
        $company2           = $this->em->getRepository(Company::class)->findAll();

        $this->assertNotEmpty($leadDomainsHit2);
        $this->assertCount(1, $leadDomainsHit2);
        $this->assertSame(1, $leadDomainsHit2[0]->getCount());
        $this->assertEquals('leuchtfeuer.com', $leadDomainsHit2[0]->getDomain());
        $this->assertNotEmpty($company2);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit2[0]);
        $this->assertInstanceOf(Company::class, $company2[0]);
        $this->assertSame($company2[0]->getId(), $companyDomainsHit2[0]->getCompany()->getId());
        $this->assertCount(1, $company2);

        // Hit 3 with the same lead, save lead domain and company domain
        $hit3              = $this->createHit($lead);
        $pageHitEventMock3 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock3->expects($this->exactly(4))->method('getHit')->willReturn($hit3);
        $pageHitEventMock3->expects($this->exactly(1))->method('getRequest')->willReturn($request);
        $floodlightSubscriber->onPageHit($pageHitEventMock3);
        $leadDomainsHit3    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit3[0]);
        $companyDomainsHit3 = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit3[0]->getDomain()]);
        $company3           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($leadDomainsHit3);
        $this->assertCount(1, $leadDomainsHit3);
        $this->assertSame(2, $leadDomainsHit3[0]->getCount());
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit3[0]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit2[0]);
        $this->assertEquals('leuchtfeuer.com', $leadDomainsHit2[0]->getDomain());
        $this->assertNotEmpty($company3);
        $this->assertInstanceOf(Company::class, $company3[0]);
        $this->assertSame($company3[0]->getId(), $companyDomainsHit3[0]->getCompany()->getId());
        $this->assertCount(1, $company3);

        // Hit 4 with the same lead, with different domain, save lead domain and company domain
        $hit4     = $this->createHit($lead);
        $request2 = new \Symfony\Component\HttpFoundation\Request();
        $request2->server->set('REMOTE_ADDR', '185.145.181.191');
        $pageHitEventMock4 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock4->expects($this->exactly(2))->method('getHit')->willReturn($hit3);
        $pageHitEventMock4->expects($this->exactly(1))->method('getRequest')->willReturn($request2);
        $floodlightSubscriber->onPageHit($pageHitEventMock4);
        $leadDomainsHit4     = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $totalCompanyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit4[1]);
        $companyDomainsHit4  = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit4[1]->getDomain()]);
        $company4            = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($leadDomainsHit4);
        $this->assertCount(2, $leadDomainsHit4);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit4[1]);
        $this->assertSame(1, $leadDomainsHit4[1]->getCount());
        $this->assertEquals('arkada-x.com', $leadDomainsHit4[1]->getDomain());
        $this->assertNotEmpty($company4);
        $this->assertCount(2, $company4);
        $this->assertCount(2, $totalCompanyDomains);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit4[0]);
        $this->assertInstanceOf(Company::class, $company4[1]);
        $this->assertSame($company4[1]->getId(), $companyDomainsHit4[0]->getCompany()->getId());
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit4[1]);
        $leadDomainsHit4[1]->setCount(5);
        $this->em->persist($leadDomainsHit4[1]);
        $this->em->flush();

        // Test recalculateLeadPrimaryCompany
        $pageHitEventMock5 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock5->expects($this->exactly(4))->method('getHit')->willReturn($hit3);
        $pageHitEventMock5->expects($this->exactly(1))->method('getRequest')->willReturn($request2);
        $floodlightSubscriber->onPageHit($pageHitEventMock5);
        $currentLead = $this->em->getRepository(Lead::class)->find($lead->getId());
        $this->assertInstanceOf(Lead::class, $currentLead);
        $this->assertInstanceOf(Company::class, $currentLead->getPrimaryCompany());
        $this->assertSame($currentLead->getPrimaryCompany()->getId(), $company4[1]->getId());
        //Stimmt das wirklich
    }

    //    public function testOnPageHitSaveWithFloodlight()
    //    {
    //        $this->updateApikey('pro_123');
    //
    //        $lead = $this->createLead();
    //        $hit = $this->createHit($lead);
    //
    //        $floodlightSubscriber = $this->createFloodlightSubescriberInstance($hit,false);
    //        $pageHitEventMock1 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
    //
    //        $lead = $this->createLead();
    //        $request = new \Symfony\Component\HttpFoundation\Request();
    //        $pageHitEventMock1->expects($this->exactly(1))->method('getHit')->willReturn($hit);
    //        $pageHitEventMock1->expects($this->exactly(1))->method('getRequest')->willReturn($request);
    //        $floodlightSubscriber->onPageHit($pageHitEventMock1);

    //        $hit2 = $this->createHit($lead);
    //        $floodlightSubscriber = $this->createFloodlightSubescriberInstance($hit2);
    //        $request = new \Symfony\Component\HttpFoundation\Request();
    //        $request->server->set('REMOTE_ADDR', '8.8.8.8');
    //        $pageHitEventMock2 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
    //        $pageHitEventMock2->expects($this->exactly(4))->method('getHit')->willReturn($hit2);
    //        $pageHitEventMock2->expects($this->exactly(1))->method('getRequest')->willReturn($request);
    //        $floodlightSubscriber->onPageHit($pageHitEventMock2);

    //        $all = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
    //        $leadDomainsHit2 = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
    //        $companyDomainsHit2 = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit2[0]->getDomain()]);
    //        $companies2 = $this->em->getRepository(Company::class)->findAll();
    //        $this->assertNotEmpty($leadDomainsHit2);
    //        $this->assertCount(1, $leadDomainsHit2);
    //        $this->assertSame(1, $leadDomainsHit2[0]->getCount());
    //        $this->assertEquals('le-non.de', $leadDomainsHit2[0]->getDomain());
    //        $this->assertNotEmpty($companies2);
    //        $this->assertCount(1, $companies2);
    //        $this->assertSame($companies2[0]->getId(), $companyDomainsHit2[0]->getCompany()->getId());
    //    }

    //    private function createFloodlightSubescriberInstance($hit, $check=true)
    //    {
    //        $ipLookupRulesHelperMock = $this->createIpRulesServiceMock($hit, $check);
    //        $floodlightSubscriber    = new FloodlightSubscriber(
    //            $ipLookupRulesHelperMock,
    //            self::getContainer()->get(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config::class),
    //            self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains'),
    //            self::getContainer()->get('logger'),
    //            self::getContainer()->get(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper::class),
    //            self::getContainer()->get('mautic.lead.model.company'),
    //            self::getContainer()->get('mautic.lead.model.lead'),
    //            self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains'),
    //        );
    //
    //        return $floodlightSubscriber;
    //    }

    //    private function createIpRulesServiceMock($hit, $check=true): \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper
    //    {
    //        $floodlightCompanyDomains = new FloodlightCompanyDomains();
    //        $floodlightCompanyDomains->setCompany(new Company());
    //        $floodlightCompanyDomains->setDomain('google.com');
    //        $floodlightCompanyDomains->setDomain('google.pt');
    //        $floodlightCompanyDomains->setDateModified(new \DateTime());
    //        $floodlightCompanyDomains->setCodeStatusRequest(null);
    //        $floodlightCompanyDomains->setEnrichmentDate(new \DateTime());
    //        $floodlightCompanyDomains->setDateAdded(new \DateTime());
    //
    //        $ipLookupRulesHelper = $this->createMock(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper::class);
    //        $ipLookupRulesHelper->expects($this->exactly(1))->method('check')->willReturn($check);
    //        $dataDomain    = json_decode(file_get_contents(__DIR__.'/../../fixtures/floodlight.json'), true);
    //        $flooglightDTO = new \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO($dataDomain);
    //        $getDomain     = [
    //            'status'  => 'valid',
    //            'domain'  => $flooglightDTO->getCompanyPrimaryDomain(),
    //            'details' => [
    //                'floodlight'        => $flooglightDTO,
    //                'statusRequestCode' => 200,
    //                'companyDomains'    => [$floodlightCompanyDomains],
    //            ],
    //        ];
    //        $ipLookupRulesHelperFunctional = self::getContainer()->get(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper::class);
    //        assert($ipLookupRulesHelperFunctional instanceof \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper);
    //        // Real object
    //
    //        $resultGetDomain                                     = $ipLookupRulesHelperFunctional->evaluate($hit, $getDomain);
    //        $resultGetDomain['result']['companyDomains']['isNew']=false;
    //
    //        $ipLookupRulesHelper->expects($this->once())->method('getDomain')->willReturn($getDomain);
    //        $ipLookupRulesHelper->expects($this->any())->method('isIp')->willReturn(true);
    //        $fakeGetDomain = [
    //            'domain' => 'google.com',
    //            'result' => [
    //                'leadDomain'     => new FloodlightLeadDomains(),
    //                'companyDomains' => [
    //                    'isNew' => true,
    //                    'data'  => [$floodlightCompanyDomains],
    //                ],
    //            ],
    //            'status' => 'valid',
    //        ];
    //        $ipLookupRulesHelper->expects($this->once())->method('evaluate')->willReturn($resultGetDomain);
    //
    //        return $ipLookupRulesHelper;
    //    }

    private function createHit(Lead $lead, string $ipAdress = '127.0.0.1'): Hit
    {
        $ip = new IpAddress();
        $ip->setIpAddress($ipAdress);
        $ip->setIpDetails(['details'=>'details']);
        $this->em->persist($ip);
        $this->em->flush();

        $rand = rand(1, 1000000000);
        $hit  = new Hit();
        $hit->setLead($lead);
        $hit->setDateHit(new \DateTime());
        $hit->setIpAddress($ip);
        $hit->setUrl('http://localhost');
        $hit->setReferer('http://localhost');
        $hit->setUserAgent('user agent');
        $hit->setPageLanguage('en');
        $hit->setCode(200);
        $hit->setTrackingId($rand.'123456');
        $this->em->persist($hit);
        $this->em->flush();

        return $hit;
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

    public function beforeTearDown(): void
    {
        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        if (file_exists($configPathTo)) {
            unlink($configPathTo);
        }
    }
}
