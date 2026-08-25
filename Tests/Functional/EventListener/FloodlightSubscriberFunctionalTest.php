<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Test\Container\TestContainer;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\FloodlightSubscriber;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\KeywordBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisService;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures\FixtureHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

#[Group('no-ci')]
final class FloodlightSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use RebuildsCompanyTableAfterCustomFieldCleanup;

    protected $useCleanupRollback = false;

    private FloodlightLeadDomainsRepository $leadDomainRepo;

    private FixtureHelper $fixtureHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->leadDomainRepo = $this->em->getRepository(FloodlightLeadDomains::class);
        $this->fixtureHelper  = new FixtureHelper($this->em, $this->client);
    }

    public function testPagehitWhenPluginNotActivated(): void
    {
        $this->activePlugin(false);
        $this->updateApikey();
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertCount(0, $totalItemsAfterRequest);
    }

    public function testPagehitWhenApiKeyNotSet(): void
    {
        $this->activePlugin();
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertCount(0, $totalItemsAfterRequest);
    }

    public function testPagehitWhenInvalidApiKey(): void
    {
        $this->activePlugin();
        $this->updateApikey('invalidAccessKeyId', 'invalidSecretKey');
        $this->client->request('GET', '/mtracking.gif');
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertCount(0, $totalItemsAfterRequest);
    }

    public function testJustOnePagehit(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $this->client->request('GET', '/mtracking.gif');
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertCount(0, $totalItemsAfterRequest);
    }

    public function testNoLookupForKnownLeads(): void
    {
        $lead = $this->createKnownLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);
        $totalItemsAfterRequest = $this->leadDomainRepo->findAll();
        $this->assertCount(0, $totalItemsAfterRequest);
    }

    public function testSuccessPagehit(): void
    {
        $lead = $this->createAnonymousLead();
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

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    public function test404Pagehit(): void
    {
        $lead = $this->createAnonymousLead();
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
        $lead = $this->createAnonymousLead();
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
        $this->assertSame(FloodlightLeadDomains::STATUS_RETRY_LIMIT_EXCEEDED, $leadDomainsHit[0]->getStatus());
    }

    public function testThirdSuccessPagehitAddsCountPlusOne(): void
    {
        $lead = $this->createAnonymousLead();
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
        $lead = $this->createAnonymousLead();
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

        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy(['domain' => $leadDomainsHit[0]->getDomain()]);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($company[0]->getId(), $companyDomainsHit[0]->getCompany()->getId());
    }

    public function testOldRetryEntryDeletedAfter404Request(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createAnonymousLead();
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
        $lead = $this->createAnonymousLead();
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
        $lead = $this->createAnonymousLead();
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
        $lead = $this->createAnonymousLead();
        $this->activePlugin();
        $this->updateApikey();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);

        $companies           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($companies);
        $this->assertCount(1, $companies);
        $company = $companies[0];
        $company->setCity('testcity');
        $company->setName('Leuchtfeuer');
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->saveEntity($company);

        $lead2 = $this->createAnonymousLead();
        $this->processHit('217.91.35.16', $lead2);
        $this->processHit('217.91.35.16', $lead2);

        $company           = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($company);
        $this->assertCount(1, $company);
        $this->assertEquals('testcity', $company[0]->getCity());
        $this->assertEquals('Leuchtfeuer', $company[0]->getName());
    }

    public function testCompanyLogoProcessing(): void
    {
        $lead = $this->createAnonymousLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit('12.138.157.209', $lead);
        $this->processHit('12.138.157.209', $lead);

        $companies = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($companies);
        $this->assertCount(1, $companies);

        $company      = $companies[0];
        $logoFileName = $company->getFieldValue('companylogo_filename');
        $this->assertIsString($logoFileName);
        $this->assertStringStartsWith('f4b_', $logoFileName);

        $pathsHelper  = self::getContainer()->get(PathsHelper::class);
        $logoFilePath = $pathsHelper->getMediaPath().'/logos/'.$logoFileName;
        $this->assertFileExists($logoFilePath);
        $this->assertContains(pathinfo($logoFilePath, PATHINFO_EXTENSION), ['png', 'jpg']);
    }

    public function testCompanyLogoNotProcessedWhenAlreadySetManually(): void
    {
        $lead = $this->createAnonymousLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit('12.138.157.209', $lead);
        $this->processHit('12.138.157.209', $lead);

        $companies = $this->em->getRepository(Company::class)->findAll();
        $company   = $companies[0];
        $company->addUpdatedField('companylogo_filename', 'manual_logo.png');
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->saveEntity($company);

        $lead2 = $this->createAnonymousLead();
        $this->processHit('12.138.157.209', $lead2);
        $this->processHit('12.138.157.209', $lead2);

        $company = $this->em->getRepository(Company::class)->find($company->getId());
        $this->assertInstanceOf(Company::class, $company);
        $this->assertEquals('manual_logo.png', $company->getFieldValue('companylogo_filename'));
    }

    public function testCompanyLogoNotProcessedWhenEmptyUrl(): void
    {
        $lead = $this->createAnonymousLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit('12.138.157.208', $lead);
        $this->processHit('12.138.157.208', $lead);

        $companies = $this->em->getRepository(Company::class)->findAll();
        $this->assertNotEmpty($companies);

        $company      = $companies[0];
        $logoFileName = $company->getFieldValue('companylogo_filename');
        $this->assertNull($logoFileName);
    }

    public function testRegularLookupWhenIgnoreTelcoSetToFalse(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        // '45.117.246.240' is ip that will return a floodlight company with icb_sector = 'Telecommunications'
        $this->updateIgnoreTelcoSettings(false);
        $lead = $this->createAnonymousLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);
    }

    public function testRegularLookupWhenCompanyHasKnownContact(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $company = $this->createCompany('Test', 'telcodomain.com');
        $this->createKnownLeadAndAddToCompany($company);
        $this->addNewCompanyDomain('telcodomain.com', $company);
        $lead = $this->createAnonymousLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->checkNumberOfLeadsAssignedToCompany(2);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);
    }

    public function testRegularLookupWhenIcbSectorIsNotTelecommunication(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createAnonymousLead();
        $this->processHit('217.91.35.16', $lead);
        $this->processHit('217.91.35.16', $lead);
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);
    }

    public function testNoLookupWhenCompanyExistsAndIsTelcoEqualsTrue(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $company = $this->createCompany('Test', 'telcodomain.com');
        $this->addNewCompanyDomain('telcodomain.com', $company, true);
        $lead = $this->createAnonymousLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);
    }

    public function testTelcoLookupWhenCompanyExistsAndIsTelcoEqualsFalse(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $company = $this->createCompany('Test', 'telcodomain.com');
        $this->addNewCompanyDomain('telcodomain.com', $company, false);
        $lead = $this->createAnonymousLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);
    }

    public function testNoLookupWhenNoExistingDomainAndNoExistingCompanyWithSameName(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $lead = $this->createAnonymousLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->assertEmpty($this->em->getRepository(Company::class)->findAll());
        $this->assertEmpty($this->em->getRepository(FloodlightCompanyDomains::class)->findAll());
    }

    public function testTelcoLookupWhenNoExistingDomainButExistingCompanyWithSameName(): void
    {
        $this->activePlugin();
        $this->updateApikey();
        $this->createCompany('Digicel Pacific');
        $lead    = $this->createAnonymousLead();
        $this->processHit('45.117.246.240', $lead);
        $this->processHit('45.117.246.240', $lead);
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);
    }

    public function test429PagehitWithFailingDnsLookupAndSuccessfulWhoisLookup(): void
    {
        // Use ip 86.101.230.57 because Mockoon will return 429 floodlight response for this IP, mock dns lookup will fail for this IP and Whois will be success for this ip
        $ip = '86.101.230.57';

        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
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

        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
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
        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
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
        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
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
        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
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

        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit($ip, $lead);
        $this->processHit($ip, $lead);

        $config = self::getContainer()->get(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config::class);
        $this->assertTrue($config->checkIfLookupReturned429ResponseInLast60Minutes());
    }

    public function testNoFloodlightLookupWhen429TimestampLessThan60MinutesAgo(): void
    {
        $ip1 = '86.101.230.58';
        $ip2 = '217.91.35.16';

        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();
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

        $this->mockExternalLookupsDependingOnIp();

        $lead = $this->createAnonymousLead();

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

    private function activePlugin(bool $isPublished = true): void
    {
        $this->fixtureHelper->createAndEnablePlugin($isPublished);
        $this->fixtureHelper->copyBlacklistConfig();
    }

    private function updateApikey(string $accessKeyId='validAccessKeyId', string $secretKey='validSecretKey'): void
    {
        $crawler = $this->client->request('GET', '/s/plugins/config/LeuchtfeuerFloodlightB2B');
        $form    = $crawler->selectButton('Save')->form();
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\InputFormField::class, $form['integration_config[apiKeys][api_access_key_id]']);
        $this->assertInstanceOf(\Symfony\Component\DomCrawler\Field\InputFormField::class, $form['integration_config[apiKeys][api_secret_key]']);
        $form['integration_config[apiKeys][api_access_key_id]']->setValue($accessKeyId);
        $form['integration_config[apiKeys][api_secret_key]']->setValue($secretKey);
        $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    private function updateIgnoreTelcoSettings(bool $ignoreTelcoSetting): void
    {
        $integration = $this->em->getRepository(Integration::class)
            ->findOneBy(['name' => 'LeuchtfeuerFloodlightB2B']);

        $this->assertInstanceOf(Integration::class, $integration);

        $featureSettings = $integration->getFeatureSettings();
        if (!is_array($featureSettings)) {
            $featureSettings = [];
        }

        if (!isset($featureSettings['integration'])) {
            $featureSettings['integration'] = [];
        }
        $featureSettings['integration']['ignore_telcos'] = $ignoreTelcoSetting ? '1' : '0';

        $integration->setFeatureSettings($featureSettings);
        $this->em->persist($integration);
        $this->em->flush();
    }

    /**
     * @param array<string, string> $headers
     */
    private function processHit(string $remoteAddress, Lead $lead, array $headers = []): void
    {
        $floodlightSubscriber = self::getContainer()->get(FloodlightSubscriber::class);
        $request              = new \Symfony\Component\HttpFoundation\Request();
        $request->server->set('REMOTE_ADDR', $remoteAddress);

        // Set proxy headers
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $hit              = $this->createHit($lead, $remoteAddress);
        $pageHitEventMock = $this->createMock(\Mautic\PageBundle\Event\PageHitEvent::class);
        $pageHitEventMock->method('getHit')->willReturn($hit);
        $pageHitEventMock->method('getRequest')->willReturn($request);
        $floodlightSubscriber->onPageHit($pageHitEventMock);
    }

    private function createHit(Lead $lead, string $ipAdress = '127.0.0.1'): Hit
    {
        $ip = new IpAddress();
        $ip->setIpAddress($ipAdress);
        $ip->setIpDetails(['details'=>'details']);
        $this->em->persist($ip);
        $this->em->flush();

        $rand = random_int(1, 1000000000);
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

    private function createAnonymousLead(): Lead
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

    private function createKnownLeadAndAddToCompany(Company $company): void
    {
        $lead         = $this->createKnownLead();
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->addLeadToCompany($company, $lead);
        $companyModel->saveEntity($company);
    }

    private function createCompany(string $name, ?string $website = null): Company
    {
        $company = new Company();
        $company->setName($name);
        if ($website) {
            $company->setWebsite($website);
        }
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

    private function addNewCompanyDomain(string $domain, Company $company, bool $isTelco = false): FloodlightCompanyDomains
    {
        $floodlightCompanyDomain = new FloodlightCompanyDomains();
        $floodlightCompanyDomain->setDateAdded(new \DateTime());
        $floodlightCompanyDomain->setDateModified(new \DateTime());
        $floodlightCompanyDomain->setDomain($domain);
        $floodlightCompanyDomain->setIsTelco($isTelco);
        $floodlightCompanyDomain->setCompany($company);
        $floodlightCompanyDomain->setIsPrimaryDomain(true);
        $this->em->persist($floodlightCompanyDomain);
        $this->em->flush();

        return $floodlightCompanyDomain;
    }

    private function checkNumberOfLeadsAssignedToCompany(int $count): void
    {
        $companyLeads          = $this->em->getRepository(CompanyLead::class)->findAll();
        $this->assertCount($count, $companyLeads);
    }

    private function checkIfCompanyDomainHasIsTelcoValue(?bool $telcoValue): void
    {
        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertNotEmpty($companyDomainsHit);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($telcoValue, $companyDomainsHit[0]->getIsTelco());
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

    private function mockExternalLookupsDependingOnIp(): void
    {
        $this->mockWhoisResultDependingOnIp();
        $this->mockDnsLookupResultDependingOnIp();
    }

    private function mockDnsLookupResultDependingOnIp(): void
    {
        $hostnames = [
            '217.91.35.16'  => 'mail.leuchtfeuer.com',
            '86.101.230.57' => '11.11.11.11',
            '86.101.230.58' => '102-220-105-89.dna.tri.bbisp.co.za',
            '86.101.230.59' => '11.11.11.11',
            '86.101.230.60' => '11.11.11.11',
            '86.101.230.61' => '11.11.11.11',
            '11.11.11.11'   => '11.11.11.11',
        ];

        $container = self::getContainer();

        $domainUtility = $this->getMockBuilder(DomainUtility::class)
            ->setConstructorArgs([
                $container->get(WhoisService::class),
                $container->get(KeywordBlacklistHelper::class),
                $container->get(DomainBlacklistHelper::class),
                $container->get(PathsHelper::class),
                $container->get(LoggerInterface::class),
            ])
            ->onlyMethods(['lookupHostname'])
            ->getMock();

        $domainUtility->method('lookupHostname')->willReturnCallback(
            fn (string $ip): string|false => $hostnames[$ip] ?? gethostbyaddr($ip)
        );

        $this->mockService(DomainUtility::class, $domainUtility);
    }

    private function mockWhoisResultDependingOnIp(): void
    {
        $rdap57 = ['name' => 'NET', 'entities' => [['vcardArray' => ['vcard', [['fn', new \stdClass(), 'text', 'AT&T Enterprises, LLC'], ['email', new \stdClass(), 'text', 'contact@NEPHRONPHARM.COM']]]]]];
        $rdap59 = ['name' => 'NET', 'entities' => [['vcardArray' => ['vcard', [['fn', new \stdClass(), 'text', 'AT&T Enterprises, LLC'], ['email', new \stdClass(), 'text', 'contact@floodlightblacklistkeyword.com']]]]]];
        $rdap60 = ['name' => 'NET', 'entities' => [['vcardArray' => ['vcard', [['fn', new \stdClass(), 'text', 'AT&T Enterprises, LLC'], ['email', new \stdClass(), 'text', 'contact@floodlightblacklistdomain.com']]]]]];

        $responses = [
            '86.101.230.57' => $rdap57,
            '86.101.230.59' => $rdap59,
            '86.101.230.60' => $rdap60,
        ];
        $failIps = ['86.101.230.58', '86.101.230.61', '127.0.0.1'];

        $whoisService = $this->getMockBuilder(WhoisService::class)
            ->setConstructorArgs([self::getContainer()->get(LoggerInterface::class)])
            ->onlyMethods(['fetchRdap'])
            ->getMock();

        $whoisService->method('fetchRdap')->willReturnCallback(function (string $ip) use ($responses, $failIps): ?array {
            if (in_array($ip, $failIps, true)) {
                return null;
            }

            return $responses[$ip] ?? null;
        });

        $this->mockService(WhoisService::class, $whoisService);
    }

    /**
     * Re-syncs the TestContainer instead of self::getContainer()->set(), which can hit an
     * uninitialized container after KernelBrowser reboots the kernel on a client request.
     */
    private function mockService(string $id, object $service): void
    {
        $rawContainer  = self::$kernel->getContainer();
        $testContainer = self::getContainer();
        $this->assertInstanceOf(TestContainer::class, $testContainer);
        $testContainer->setPublicContainer($rawContainer);
        $testContainer->set($id, $service);
    }

    /**
     * Test that client IP is correctly extracted from various proxy headers.
     *
     * @param array<string, string> $headers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('proxyHeaderDataProvider')]
    public function testClientIpExtractedFromProxyHeaders(
        string $remoteAddr,
        array $headers,
        string $expectedDomain,
        string $testDescription,
    ): void {
        $lead = $this->createAnonymousLead();
        $this->activePlugin();
        $this->updateApikey();

        $this->processHit($remoteAddr, $lead, $headers);
        $this->processHit($remoteAddr, $lead, $headers);

        $leadDomainsHit = $this->em->getRepository(FloodlightLeadDomains::class)->findBy(['contact' => $lead]);
        $this->assertNotEmpty($leadDomainsHit, $testDescription);
        $this->assertCount(1, $leadDomainsHit);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainsHit[0]);
        $this->assertSame(FloodlightLeadDomains::STATUS_SUCCESS, $leadDomainsHit[0]->getStatus());
        $this->assertSame($expectedDomain, $leadDomainsHit[0]->getDomain(), $testDescription);
        $this->assertSame(FloodlightLeadDomains::LOOKUP_TYPE_API, $leadDomainsHit[0]->getLookupType());
    }

    public static function proxyHeaderDataProvider(): \Generator
    {
        yield 'X-Forwarded-For header with single IP' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['X-Forwarded-For' => '217.91.35.16'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should extract IP from X-Forwarded-For header',
        ];

        yield 'X-Forwarded-For header with multiple IPs (uses first)' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['X-Forwarded-For' => '217.91.35.16, 192.168.1.1, 10.0.0.5'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should use first IP from X-Forwarded-For chain',
        ];

        yield 'CF-Connecting-IP header (Cloudflare)' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['CF-Connecting-IP' => '217.91.35.16'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should extract IP from CF-Connecting-IP header',
        ];

        yield 'CF-Connecting-IP takes precedence over X-Forwarded-For' => [
            'remoteAddr' => '10.0.0.1',
            'headers'    => [
                'CF-Connecting-IP' => '217.91.35.16',
                'X-Forwarded-For'  => '127.0.0.1',
            ],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'CF-Connecting-IP should override X-Forwarded-For',
        ];

        yield 'True-Client-IP header (Akamai)' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['True-Client-IP' => '217.91.35.16'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should extract IP from True-Client-IP header',
        ];

        yield 'True-Client-IP takes precedence over X-Forwarded-For' => [
            'remoteAddr' => '10.0.0.1',
            'headers'    => [
                'True-Client-IP'  => '217.91.35.16',
                'X-Forwarded-For' => '127.0.0.1',
            ],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'True-Client-IP should override X-Forwarded-For',
        ];

        yield 'Fastly-Client-IP header' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['Fastly-Client-IP' => '217.91.35.16'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should extract IP from Fastly-Client-IP header',
        ];

        yield 'X-Real-IP header (nginx)' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['X-Real-IP' => '217.91.35.16'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should extract IP from X-Real-IP header',
        ];

        yield 'X-Real-IP takes precedence over X-Forwarded-For' => [
            'remoteAddr' => '10.0.0.1',
            'headers'    => [
                'X-Real-IP'       => '217.91.35.16',
                'X-Forwarded-For' => '127.0.0.1',
            ],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'X-Real-IP should override X-Forwarded-For',
        ];

        yield 'Forwarded header (RFC 7239) with single IP' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['Forwarded' => 'for=217.91.35.16; proto=https'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should parse IP from RFC 7239 Forwarded header',
        ];

        yield 'Forwarded header with multiple entries (uses first)' => [
            'remoteAddr'      => '10.0.0.1',
            'headers'         => ['Forwarded' => 'for=217.91.35.16, for=192.168.1.1'],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should use first entry from Forwarded header chain',
        ];

        yield 'Forwarded takes precedence over X-Forwarded-For' => [
            'remoteAddr' => '10.0.0.1',
            'headers'    => [
                'Forwarded'       => 'for=217.91.35.16; proto=https',
                'X-Forwarded-For' => '127.0.0.1',
            ],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Forwarded should override X-Forwarded-For',
        ];

        yield 'No proxy headers (falls back to REMOTE_ADDR)' => [
            'remoteAddr'      => '217.91.35.16',
            'headers'         => [],
            'expectedDomain'  => 'leuchtfeuer.com',
            'testDescription' => 'Should fall back to REMOTE_ADDR when no proxy headers present',
        ];
    }
}
