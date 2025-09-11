<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Facade\ReloadFacade;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\FloodlightSubscriber;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class FloodlightSubscriberFunctionalTest extends MauticMysqlTestCase
{
    private FloodlightLeadDomainsRepository $leadDomainRepo;

    public function setUp(): void
    {
        parent::setUp();

        $this->leadDomainRepo     = $this->em->getRepository(FloodlightLeadDomains::class);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    private function updateApikey(string $accessKeyId='validAccessKeyId', string $secretKey='validSecretKey'): void
    {
        $crawler = $this->client->request('GET', '/s/plugins/config/LeuchtfeuerFloodlightB2B');
        $form    = $crawler->selectButton('Save')->form();
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\InputFormField::class, $form['integration_config[apiKeys][api_access_key_id]']);
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\InputFormField::class, $form['integration_config[apiKeys][api_secret_key]']);
        $form['integration_config[apiKeys][api_access_key_id]']->setValue($accessKeyId);
        $form['integration_config[apiKeys][api_secret_key]']->setValue($secretKey);
        $crawler = $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    private function updateIgnoreTelcoSettings(bool $ignoreTelcoSetting): void
    {
        $integration = $this->em->getRepository(Integration::class)
            ->findOneBy(['name' => 'LeuchtfeuerFloodlightB2B']);

        $featureSettings = $integration->getFeatureSettings() ?? [];

        if (!isset($featureSettings['integration'])) {
            $featureSettings['integration'] = [];
        }
        $featureSettings['integration']['ignore_telcos'] = $ignoreTelcoSetting ? '1' : '0';

        $integration->setFeatureSettings($featureSettings);
        $this->em->persist($integration);
        $this->em->flush();
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
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }
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
        $this->activePlugin();
        $this->updateApikey('invalidAccessKeyId', 'invalidSecretKey');
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    public function testJustOnePagehit(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertEquals(0, count($totalItemsAfterRequest));
    }

    public function testSuccessPagehit(): void
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
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_API, $leadDomainsHit[0]->getLookupType());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    public function test404Pagehit(): void
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
        $this->assertNull($leadDomainsHit[0]->getDomain());
        $this->assertSame('404hash', $leadDomainsHit[0]->getIpHash());
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_API, $leadDomainsHit[0]->getLookupType());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertEmpty($companyDomainsHit);
    }

    public function test202PagehitThenAnotherOneThatExceedsRetryLimitAfterTwoHours(): void
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('retryip', $lead);
        $this->processHit('retryip', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertSame(FloodlightLeadDomains::STATUS_RETRY, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertNull($leadDomainsHit[0]->getDomain());
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_API, $leadDomainsHit[0]->getLookupType());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertEmpty($companyDomainsHit);

        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);

        $this->processHit('retryip', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);

        $this->processHit('retryip', $lead);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::STATUS_RETRY_LIMIT_EXCEEDED, $leadDomainsHit[0]->getStatus());
    }

    public function testThirdSuccessPagehitAddsCountPlusOne(): void
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
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

    public function testOldRetryEntryDeletedAfter200Request(): void
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

    public function testOldRetryEntryDeletedAfter404Request(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createLead();
        $this->createHit($lead);
        $retryLeadDomain = $this->addNewLeadDomain($lead, '404hash', 1, FloodlightLeadDomains::STATUS_RETRY);
        $this->setBackDateModifiedByTwoHours($retryLeadDomain);

        $this->processHit('127.0.0.1', $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertNotEmpty($leadDomainsHit);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::STATUS_NO_DATA_FOUND, $leadDomainsHit[0]->getStatus());
        $this->assertSame(1, $leadDomainsHit[0]->getCount());
        $this->assertNull($leadDomainsHit[0]->getDomain());
    }

    /**
     * Description: When there was a recent lookup, no new lookup should be done. So the count of the success entry should not increase after the first lookup.
     */
    public function testNoLookupIfAlreadyRecentLookup(): void
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

    public function testPrimaryCompanySetCorretcly(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createLead();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);
        $this->processHit('217.91.35.16', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);
        $this->processHit('185.145.181.191', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[1]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[1]);
        // Leuchtfeuer should be primary company because domain has count 2 and Arkada-X has only count 1
        $primaryCompany = $lead->getPrimaryCompany();
        $this->assertInstanceOf(Company::class, $primaryCompany);
        $this->assertSame('Leuchtfeuer Digital Marketing GmbH', $primaryCompany->getName());

        $this->processHit('185.145.181.191', $lead);
        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[1]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[1]);
        // Arkada-X should be primary company because domain has count 2 like Leuchtfeuer, but the date modified of the entry is more recent
        $primaryCompany = $lead->getPrimaryCompany();
        $this->assertInstanceOf(Company::class, $primaryCompany);
        $this->assertSame('Arkada-X', $primaryCompany->getName());

        $leadDomain = $this->addNewLeadDomain($lead, 'iphash2', 2, FloodlightLeadDomains::STATUS_SUCCESS);
        $leadDomain->setDomain('leuchtfeuer.com');
        $this->em->persist($leadDomain);
        $this->em->flush();
        $this->processHit('185.145.181.191', $lead);
        // Leuchtfeuer should be primary company because the count of both lead domain entries for domain 'leuchtfeuer.com' get aggregated, so the count is 4 and the count for the Arkada-X domain is only three.
        $primaryCompany = $lead->getPrimaryCompany();
        $this->assertInstanceOf(Company::class, $primaryCompany);
        $this->assertSame('Leuchtfeuer Digital Marketing GmbH', $primaryCompany->getName());
    }

    /**
     * Description: Company data from manual edits, etc. shouldn't be overridden by Floodlight.
     */
    public function testProtectNonF4bCompanyData(): void
    {
        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);

        $companies           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($companies);
        $this->assertCount(1, $companies);
        $this->assertInstanceOf(Company::class, $companies[0]);
        $company = $companies[0];
        $company->setCity('testcity');
        $company->setName('Leuchtfeuer');
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->saveEntity($company);

        $lead2 = $this->createLead();
        $this->processHit('217.91.35.16', $lead2);
        $this->processHit('217.91.35.16', $lead2);

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);
        $this->assertEquals('testcity', $company[0]->getCity());
        $this->assertEquals('Leuchtfeuer', $company[0]->getName());
    }

    public function testIfIgnoreTelcoLogicWorks(): void
    {
        $this->activePlugin();
        $this->updateApikey();

        // '45.117.246.240' is ip that will return a floodlight company with icb_sector = 'Telecommunications'

        // Pagehit when ignore_telco = false
        $this->updateIgnoreTelcoSettings(false);
        $lead = $this->createLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->assertEquals(true, $this->checkIfCompanyPublished());
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(null);

        $this->updateIgnoreTelcoSettings(true);

        // Pagehit when ignore_telco = true,companydomain already exists with is_telco = null, and ip will return icb_sector which is NOT 'Telecommunications'
        $lead = $this->createLead();
        // '45.117.246.241' returns same company as '45.117.246.240' but with icb_sector = 'technology'
        $this->processHit('45.117.246.241', $lead);
        $this->processHit('45.117.246.241', $lead);
        $this->assertEquals(true, $this->checkIfCompanyPublished());
        $this->checkNumberOfLeadsAssignedToCompany(2);
        $this->checkIfCompanyDomainHasIsTelcoValue(null);

        // Pagehit when ignore_telco = true,companydomain already exists with is_telco = null, and ip will return icb_sector = 'Telecommunications'
        $lead = $this->createLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->assertEquals(false, $this->checkIfCompanyPublished());
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);

        // Pagehit when ignore_telco = true and companydomain already exists with is_telco = true
        $lead = $this->createLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->assertEquals(false, $this->checkIfCompanyPublished()); // doesn't get changed at all
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);

        // Pagehit when ignore_telco = true and companydomain already exists and has known contact
        $company = $this->em->getRepository(Company::class)->findOneBy([]);
        $this->createKnownLeadAndAddToCompany($company);
        $lead = $this->createLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->publishCompany(true);
        $this->checkNumberOfLeadsAssignedToCompany(2);
        // Sollte hier nicht eigentlich auch auf is_telco === false zurückgesetzt werden?
        $this->checkIfCompanyDomainHasIsTelcoValue(true);

        // Pagehit when ignore_telco = true, no existing company domain and ip will return icb_sector = 'Telecommunications'
        $this->clearTestTables();
        $lead = $this->createLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->assertEquals(false, $this->checkIfCompanyPublished());
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);

        // Pagehit when ignore_telco = true, no existing company domain and ip will return icb_sector which is NOT 'Telecommunications'
        $this->clearTestTables();
        $lead = $this->createLead();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);
        $this->assertEquals(true, $this->checkIfCompanyPublished());
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);

        // Telco company doesn't get unpublished if it already existed before floodlight lookup
        $this->clearTestTables();
        $company = $this->createCompany('Digicel Pacific');
        $lead    = $this->createLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->assertEquals(true, $this->checkIfCompanyPublished());
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);
    }

    private function clearTestTables(): void
    {
        // Alle Entitäten direkt über Doctrine löschen
        $this->em->createQueryBuilder()
            ->delete(CompanyLead::class)
            ->getQuery()
            ->execute();

        $this->em->createQueryBuilder()
            ->delete(Company::class)
            ->getQuery()
            ->execute();

        $this->em->createQueryBuilder()
            ->delete(Lead::class)
            ->getQuery()
            ->execute();

        $this->em->flush();
        $this->em->clear();
    }

    private function checkNumberOfLeadsAssignedToCompany(int $count)
    {
        $companyLeads          = $this->em->getRepository(CompanyLead::class)->findAll();
        // $this->assertNotEmpty($companyLeads );
        $this->assertCount($count, $companyLeads);
    }

    private function checkIfCompanyDomainHasIsTelcoValue(?bool $telcoValue)
    {
        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertNotEmpty($companyDomainsHit);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertEquals($telcoValue, $companyDomainsHit[0]->getIsTelco());
    }

    private function createKnownLeadAndAddToCompany(Company $company): void
    {
        $lead         = $this->createKnownLead();
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->addLeadToCompany($company, $lead);
        $companyModel->saveEntity($company);
    }

    private function checkIfCompanyPublished(): bool
    {
        $company = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);

        return $company[0]->isPublished();
    }

    private function publishCompany(bool $published): void
    {
        $company = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);
        $company[0]->setIsPublished($published);
        $this->em->persist($company[0]);
        $this->em->flush();
    }

    public function test429PagehitWithFailingDnsLookupAndSuccessfulWhoisLookup(): void
    {
        // Use ip 86.101.230.57 because Mockoon will return 429 floodlight response for this IP, mock dns lookup will fail for this IP and Whois will be success for this ip
        $ip = '86.101.230.57';

        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_WHOIS, $leadDomainsHit[0]->getLookupType());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);
        $this->assertSame('nephronpharm.com', $company[0]->getName());

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    public function test429PagehitWithSuccessfulDnsLookup(): void
    {
        // Use ip 86.101.230.58 because Mockoon will return 429 floodlight response for this IP and mock DNS lookup will be successful for this IP (returns bbisp.co.za domain)
        $ip = '86.101.230.58';

        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_DNS, $leadDomainsHit[0]->getLookupType());
        $this->assertSame('bbisp.co.za', $leadDomainsHit[0]->getDomain());

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertInstanceOf(Company::class, $company[0]);
        $this->assertSame('bbisp.co.za', $company[0]->getName());

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    public function test429PagehitWithFailingDnsLookupAndWhoisLookupWithBlacklistedKeywordInDomain(): void
    {
        // Use ip 86.101.230.59 because Mockoon will return 429 floodlight response for this IP, mock dns lookup will fail for this IP and Whois will return result with blacklisted keyword in domain
        $ip = '86.101.230.59';
        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertEmpty($leadDomainsHit);

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEmpty($companyDomainsHit);
    }

    public function test429PagehitWithFailingDnsLookupAndWhoisLookupWithBlacklistedDomain(): void
    {
        // Use ip 86.101.230.60 because Mockoon will return 429 floodlight response for this IP, mock dns lookup will fail for this IP and Whois will return result with blacklisted domain
        $ip = '86.101.230.60';
        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertEmpty($leadDomainsHit);

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEmpty($companyDomainsHit);
    }

    public function test429PagehitWithFailingDnsLookupAndWhoisLookupWithError(): void
    {
        // Use ip 86.101.230.61 because Mockoon will return 429 floodlight response for this IP, mock dns lookup will fail for this IP and Whois will return an error
        $ip = '86.101.230.61';
        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $leadDomainsHit    = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertEmpty($leadDomainsHit);

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertEmpty($company);

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertEmpty($companyDomainsHit);
    }

    public function test429TimestampSetAfter429FloodlightLookup(): void
    {
        $ip = '86.101.230.58';

        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $config = self::getContainer()->get('MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config');
        $this->assertTrue($config->checkIfLookupReturned429ResponseInLast60Minutes());
    }

    public function testNoFloodlightLookupWhen429TimestampLessThan60MinutesAgo(): void
    {
        $ip1 = '86.101.230.58';
        $ip2 = '217.91.35.16';

        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit($ip1, $lead);
        $this->processHit($ip1, $lead);

        $leadDomainsHit = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->setBackDateModifiedByTwoHours($leadDomainsHit[0]);

        $this->processHit($ip2, $lead);

        $leadDomainsHit = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertCount(2, $leadDomainsHit);

        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[1]);
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_DNS, $leadDomainsHit[0]->getLookupType());
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_DNS, $leadDomainsHit[1]->getLookupType());
    }

    public function testFloodlightLookupIsUsedWhen429TimestampIsMoreThan60MinutesAgo(): void
    {
        $ip = '217.91.35.16';

        $this->mockDnsLookupResultDependingOnIp();
        $this->mockWhoisResultDependingOnIp();

        $lead = $this->createLead();

        $this->activePlugin();
        $this->updateApikey();
        $this->setOutdated429Timestamp();

        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $leadDomainsHit = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_API, $leadDomainsHit[0]->getLookupType());
    }

    private function setOutdated429Timestamp(): void
    {
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerFloodlightB2B']);
        $this->assertInstanceOf(Integration::class, $integration);

        $featureSettings = $integration->getFeatureSettings();

        if (!is_array($featureSettings)) {
            $featureSettings = [];
        }

        if (!isset($featureSettings['integration']) || !is_array($featureSettings['integration'])) {
            $featureSettings['integration'] = [];
        }

        $featureSettings['integration']['last_429_timestamp'] = time() - (61 * 60);
        $integration->setFeatureSettings($featureSettings);
        $this->em->persist($integration);
        $this->em->flush();
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
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    private function createKnownLead(): Lead
    {
        $lead = new Lead();
        $lead->setEmail('test@test.com');
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    private function createCompany($name): Company
    {
        $company = new Company();
        $company->setName($name);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function setBackDateModifiedByTwoHours(FloodlightLeadDomains $leadDomain): void
    {
        $leadDomain->setDateModified(new \DateTime('-2 hours'));
        $this->em->persist($leadDomain);
        $this->em->flush();
    }

    private function addNewLeadDomain(Lead $lead, string $ipHash, int $count, string $status): FloodlightLeadDomains
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

    private function processHit(string $remoteAddress, Lead $lead): void
    {
        $floodlightSubscriber = self::getContainer()->get(FloodlightSubscriber::class);
        $request              = new \Symfony\Component\HttpFoundation\Request();
        $request->server->set('REMOTE_ADDR', $remoteAddress);
        $hit              = $this->createHit($lead, $remoteAddress);
        $pageHitEventMock = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock->expects($this->any())->method('getHit')->willReturn($hit);
        $pageHitEventMock->expects($this->any())->method('getRequest')->willReturn($request);
        $floodlightSubscriber->onPageHit($pageHitEventMock);
    }

    private function mockDnsLookupResultDependingOnIp(): void
    {
        // Check if already defined
        if (function_exists('MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Helper\\Utility\\gethostbyaddr')) {
            return;
        }

        // Define all cases once
        $mockCode = "
        namespace MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Helper\\Utility {
            function gethostbyaddr(\$ip) {
                switch (\$ip) {
                    case '217.91.35.16':
                        return 'mail.leuchtfeuer.com';
                    case '86.101.230.57':
                        return '11.11.11.11';
                    case '86.101.230.58':
                        return '102-220-105-89.dna.tri.bbisp.co.za';
                    case '86.101.230.59':
                    case '86.101.230.60':
                    case '86.101.230.61':
                    case '11.11.11.11':
                        return '11.11.11.11';
                    default:
                        return \\gethostbyaddr(\$ip);
                }
            }
        }";

        eval($mockCode);
    }

    private function mockWhoisResultDependingOnIp(): void
    {
        if (function_exists('MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Services\\shell_exec')) {
            return;
        }

        $mockCode = "
        namespace MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Services {
            function shell_exec(\$command) {
                if (strpos(\$command, '86.101.230.57') !== false && strpos(\$command, 'main.py') !== false) {
                    return \"{'company_domain': 'NEPHRONPHARM.COM', 'company_name': 'AT&T Enterprises, LLC', 'is_isp': False, 'is_dialup': False}\\n\";
                }
                
                if (strpos(\$command, '86.101.230.58') !== false && strpos(\$command, 'main.py') !== false) {
                    return 'Error: ...';
                }
                
                if (strpos(\$command, '86.101.230.59') !== false && strpos(\$command, 'main.py') !== false) {
                    return \"{'company_domain': 'floodlightblacklistkeyword.com', 'company_name': 'AT&T Enterprises, LLC', 'is_isp': False, 'is_dialup': False}\\n\";
                }
                
                if (strpos(\$command, '86.101.230.60') !== false && strpos(\$command, 'main.py') !== false) {
                    return \"{'company_domain': 'floodlightblacklistdomain.com', 'company_name': 'AT&T Enterprises, LLC', 'is_isp': False, 'is_dialup': False}\\n\";
                }
                
                if (strpos(\$command, '86.101.230.61') !== false && strpos(\$command, 'main.py') !== false) {
                    return 'Error: IPv4 address 127.0.0.1 is already defined as Loopback via RFC 1122, Section 3.2.1.3.';
                }
                
                if (strpos(\$command, '127.0.0.1') !== false) {
                    return 'Error: IPv4 address 127.0.0.1 is already defined as Loopback via RFC 1122, Section 3.2.1.3.';
                }
                
                return \\shell_exec(\$command);
            }
        }";

        eval($mockCode);
    }
}
