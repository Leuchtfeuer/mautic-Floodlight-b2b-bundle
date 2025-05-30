<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\EventListener;

use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
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
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    private function updateApikey(string $key='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjMwLCJmcmVlIjpmYWxzZSwiaWF0IjoxNzQxMjY5MTM0fQ.X2hk5QvfRP2JhpgsaUO-Q_fQftKPEat9Ibr9ANBKw00'): void
    {
        $crawler = $this->client->request('GET', '/s/plugins/config/LeuchtfeuerFloodlightB2B');
        $form    = $crawler->selectButton('Save')->form();
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\InputFormField::class, $form['integration_config[apiKeys][api_key]']);
        $form['integration_config[apiKeys][api_key]']->setValue($key);
        $crawler = $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
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

    public function testPagehitWhenPluginNotActivated(): void
    {
        $this->activePlugin(false);
        $this->updateApikey();
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    public function testPagehitWhenApiKeyNotSet(): void
    {
        $this->activePlugin();
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    public function testPagehitWhenInvalidApiKey(): void
    {
        $this->activePlugin(true);
        $this->updateApikey('invalidapikey');
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    public function testJustOnePagehit()
    {
        $this->activePlugin();
        $this->updateApikey();
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    public function testSuccessPagehit()
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertSame('leuchtfeuer.com', $leadDomainsHit[0]->getDomain());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    public function test404Pagehit()
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('127.0.0.1', $lead);
        $this->processHit('127.0.0.1', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_NO_DATA_FOUND, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertSame(null, $leadDomainsHit[0]->getDomain());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertEmpty($companyDomainsHit);
    }

    public function disabledtest202PagehitThenAnotherOneThatExceedsRetryLimitAfterTwoHours()
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('retryIp', $lead);
        $this->processHit('retryIp', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_RETRY, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertSame(null, $leadDomainsHit[0]->getDomain());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertEmpty($companyDomainsHit);

        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);

        $this->processHit('retryIp', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::STATUS_RETRY_LIMIT_EXCEEDED, $leadDomainsHit[0]->getStatus());
    }

    public function testThirdSuccessPagehitAddsCountPlusOne()
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);
        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame(2, $leadDomainsHit[0]->getCount());
        $this->assertSame('leuchtfeuer.com', $leadDomainsHit[0]->getDomain());
    }

    public function testOldRetryEntryDeletedAfter200Request()
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createLead();
        $this->createHit($lead);
        $retryLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);
        $this->setBackDateModifiedByTwoHours($retryLeadDomain);

        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertSame('leuchtfeuer.com', $leadDomainsHit[0]->getDomain());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    //@TODO Implement once right 404 Response structure from floodlight server is in place
    public function disabledtestOldRetryEntryDeletedAfter404Request()
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createLead();
        $this->createHit($lead);
        $retryLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);
        $this->setBackDateModifiedByTwoHours($retryLeadDomain);

        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertSame('leuchtfeuer.com', $leadDomainsHit[0]->getDomain());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    /**
     * Description: When there was a recent lookup, no new lookup should be done. So the count of the success entry should not increase after the first lookup
     */
    public function testNoLookupIfAlreadyRecentLookup()
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(1, $leadDomainsHit[0]->getCount());

        $this->processHit('217.91.35.16', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
    }

    private function addNewLeadDomain($lead, string $ipHash, int $count, $status): FloodlightLeadDomains
    {
        $floodlightLeadDomain = new FloodlightLeadDomains();
        $floodlightLeadDomain->setDateAdded(new \DateTime());
        $floodlightLeadDomain->setIpHash($ipHash);
        $floodlightLeadDomain->setCount($count);
        $floodlightLeadDomain->setStatus($status);
        $floodlightLeadDomain->setContact($lead);
        $this->em->persist($floodlightLeadDomain);
        $this->em->flush();

        return $floodlightLeadDomain;
    }


    private function processHit($remoteAddress, $lead)
    {
        $floodlightSubscriber = self::getContainer()->get(FloodlightSubscriber::class);
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->server->set('REMOTE_ADDR', $remoteAddress);
        $hit              = $this->createHit($lead, $remoteAddress);
        $pageHitEventMock = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock->expects($this->any())->method('getHit')->willReturn($hit);
        $pageHitEventMock->expects($this->any())->method('getRequest')->willReturn($request);
        $floodlightSubscriber->onPageHit($pageHitEventMock);
    }



    //@TODO Klären, ob Implementation von Blacklisted bei Floodlight Lookup nötig
    public function disabledtestIpLookUpBlacklisted(): void
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

        $blacklistHelper = new DomainBlacklistHelper();
        $blacklistHelper->add('leuchtfeuer.com');

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

        $lastItem          = $this->leadDomainRepo->findOneBy([], ['id' => 'DESC']);
        $lastCompany       = $this->companyRepo->findOneBy([], ['id' => 'DESC']);
        $lastCompanyDomain = $this->companyDomainRepo->findOneBy([], ['company' => 'ASC', 'domain' => 'ASC']);
        assert($lastItem instanceof FloodlightLeadDomains);
        $this->assertEquals(1, $lastItem->getCount());
        $this->assertEmpty($lastCompany);
        $this->assertEmpty($lastCompanyDomain);
        $blacklistHelper->remove('leuchtfeuer.com');
        $this->assertEquals('blacklisted', $lastItem->getStatus());
    }


    public function testPrimaryCompanySetCorretcly(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createLead();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);
        $this->processHit('217.91.35.16', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);

        $this->processHit('185.145.181.191', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[1]);
        $this->assertSame('Leuchtfeuer Digital Marketing GmbH', $lead->getPrimaryCompany()->getName());

        $this->processHit('185.145.181.191', $lead);
        $this->assertSame('Arkada-X', $lead->getPrimaryCompany()->getName());
    }


    //@TODO klären ob nötig
    public function disabledtestNoNewCompanyCreatedWhenAlreadyExistedBeforeFloodlight(){
        $floodlightSubscriber = self::getContainer()->get(FloodlightSubscriber::class);
        $this->updateApikey('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjMwLCJmcmVlIjpmYWxzZSwiaWF0IjoxNzQxMjY5MTM0fQ.X2hk5QvfRP2JhpgsaUO-Q_fQftKPEat9Ibr9ANBKw00');
        assert($floodlightSubscriber instanceof FloodlightSubscriber);

        $pageHitEventMock1 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);

        $lead = $this->createLead();
        $hit  = $this->createHit($lead, '217.91.35.16');

        $pageHitEventMock1->expects($this->exactly(1))->method('getHit')->willReturn($hit);

        $floodlightSubscriber->onPageHit($pageHitEventMock1);

        //createCompanyLeuchtfeuer
        $companyLeuchtfeuer = new Company();
        $companyLeuchtfeuer->setName('Leuchtfeuer Digital Marketing GmbH');
        //$companyLeuchtfeuer->setWebsite('https://leuchtfeuer.com/marketing-technologie/');

        $this->em->persist($companyLeuchtfeuer);
        $this->em->flush();

        $companies           = $this->em->getRepository(Company::class)->findAll();
        $leadDomains    = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertCount(1,$companies);
        $this->assertEmpty($leadDomains);
        $this->assertEmpty($companyDomains);



        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->server->set('REMOTE_ADDR', '217.91.35.16');
        $hit2              = $this->createHit($lead, '217.91.35.16');
        $pageHitEventMock2 = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock2->expects($this->exactly(2))->method('getHit')->willReturn($hit2);
        $pageHitEventMock2->expects($this->exactly(1))->method('getRequest')->willReturn($request);
        $floodlightSubscriber->onPageHit($pageHitEventMock2);
        $leadDomains    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomains[0]);
        $companyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomains[0]->getDomain()]);
        $companies          = $this->em->getRepository(Company::class)->findAll();
        $this->assertCount(1,$leadDomains);
        $this->assertCount(1,$companyDomains);
        $this->assertCount(1,$companies);


    }

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

    public function setBackDateModifiedByTwoHours(FloodlightLeadDomains $leadDomain): void
    {
        $leadDomain->setDateModified(new \DateTime('-2 hours'));
        $this->em->persist($leadDomain);
        $this->em->flush();
    }
}
