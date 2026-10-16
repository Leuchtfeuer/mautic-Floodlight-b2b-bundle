<?php

declare(strict_types=1);

namespace LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures\FixtureHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;

/**
 * @group no-ci
 */
class FloodlightLeadDomainsModelTest extends MauticMysqlTestCase
{
    use RebuildsCompanyTableAfterCustomFieldCleanup;

    protected $useCleanupRollback = false;

    public function setUp(): void
    {
        parent::setUp();
        $fixtureHelper = new FixtureHelper($this->em, $this->client);
        $fixtureHelper->createAndEnablePlugin();
        $fixtureHelper->copyBlacklistConfig();
    }

    public function testGetEntity(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get(FloodlightLeadDomainsModel::class);
        assert($floodlightLeadDomainsModel instanceof FloodlightLeadDomainsModel);
        $leadDomain       = $this->addNewLeadDomainModel();
        $leadDomainId     = $leadDomain->getId();
        $leadDomainEntity = $floodlightLeadDomainsModel->getEntity($leadDomainId);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainEntity);
        $this->assertEquals($leadDomainId, $leadDomainEntity->getId());
        $this->assertInstanceOf(Lead::class, $leadDomainEntity->getContact());
    }

    public function disabledtestAddNewLeadDomainOrCountPlusOneInLeadDomainLeadFloodlightDetailsWithLeadDomainExisted(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get(FloodlightLeadDomainsModel::class);
        $fieldModel                 = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility              = self::getContainer()->get(DomainUtility::class);
        assert($floodlightLeadDomainsModel instanceof FloodlightLeadDomainsModel);
        $lead          = new Lead();
        $domain        = 'leuchtfeuer.com';
        $leadDomain    = $this->addNewLeadDomainModel($domain);
        $domain        = $leadDomain->getDomain();
        $fileContent   = file_get_contents(__DIR__.'/../../fixtures/floodlight.json');
        $this->assertNotFalse($fileContent);
        $dataJson      = json_decode($fileContent, true);
        $this->assertIsArray($dataJson);
        $floodlightDTO = new FloodlightDTO($dataJson, $fieldModel, $domainUtility);
        $dataDomain    = [
            'domain'     => $domain,
            'status'     => 'valid',
            'lookupType' => FloodlightLeadDomains::LOOKUP_TYPE_API,
            'details'    => [
                'floodlight'        => $floodlightDTO,
                'statusRequestCode' => 200,
                'companyDomains'    => [new FloodlightCompanyDomains()],
            ],
        ];
        $this->assertNotSame('local', $floodlightDTO->getIpHash());
        $this->assertNotNull($leadDomain->getContact());
        $result = $floodlightLeadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($leadDomain->getContact(), $dataDomain);
        $this->assertSame('local', $result->getIpHash());
        $this->assertSame(1, $result->getCount());
    }

    public function testAddNewLeadDomainOrCountPlusOneInLeadDomainLeadFloodlightDetailsWithLeadDomainNotExist(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get(FloodlightLeadDomainsModel::class);
        $fieldModel                 = self::getContainer()->get('mautic.lead.model.field');
        $domainUtility              = self::getContainer()->get(DomainUtility::class);
        assert($floodlightLeadDomainsModel instanceof FloodlightLeadDomainsModel);
        $domain        = 'lfexample.com';
        $lead          = $this->addLead();
        $fileContent   = file_get_contents(__DIR__.'/../../fixtures/floodlight.json');
        $this->assertNotFalse($fileContent);
        $dataJson      = json_decode($fileContent, true);
        $this->assertIsArray($dataJson);
        $floodlightDTO = new FloodlightDTO($dataJson, $fieldModel, $domainUtility);
        $dataDomain    = [
            'domain'     => $domain,
            'status'     => 'valid',
            'lookupType' => FloodlightLeadDomains::LOOKUP_TYPE_API,
            'details'    => [
                'floodlight'        => $floodlightDTO,
                'statusRequestCode' => 200,
                'companyDomains'    => [new FloodlightCompanyDomains()],
            ],
        ];
        $this->assertNotSame('local', $floodlightDTO->getIpHash());
        $result = $floodlightLeadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $dataDomain);
        $this->assertSame($floodlightDTO->getIpHash(), $result->getIpHash());
        $this->assertSame(1, $result->getCount());
        $this->assertSame($result->getDomain(), $domain);
    }

    public function disabledtestIsBlackListed(): void
    {
        $domainUtility = self::getContainer()->get(DomainUtility::class);
        $domain        = 'lfexample.com';
        $leadDomain    = $this->addNewLeadDomainModel($domain);
        $domain        = $leadDomain->getDomain();
        $this->assertNotNull($domain);
        $this->assertFalse($domainUtility->isBlackListed($domain));

        $domainBalcklistHelper = self::getContainer()->get(DomainBlacklistHelper::class);
        assert($domainBalcklistHelper instanceof DomainBlacklistHelper);
        $domainBalcklistHelper->add($domain);
        $this->assertTrue($domainUtility->isBlackListed($domain));

        $leadDomainTwo = $this->addNewLeadDomainModel('test.lfexample.com');
        $domainTwo     = $leadDomainTwo->getDomain();
        $this->assertNotNull($domainTwo);
        $this->assertTrue($domainUtility->isBlackListed($domainTwo));

        $domainBalcklistHelper->remove($domain);
    }

    public function testRecauculatLeadPrimaryCompany(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get(FloodlightLeadDomainsModel::class);
        assert($floodlightLeadDomainsModel instanceof FloodlightLeadDomainsModel);
        $company = $this->addNewCompany();
        $lead    = $this->addLead();
        // Set primary company
        $lead->setPrimaryCompany($company);
        $this->em->persist($lead);
        $this->em->flush();

        $this->assertSame($lead->getPrimaryCompany(), $company);

        $domain1 = 'lfexample.com';
        $domain2 = 'test.lfexample.com';

        $leadDomain1 = $this->addNewLeadDomainModel($domain2);
        $leadDomain1->setContact($lead);
        $leadDomain1->setCount(5);
        $this->em->persist($leadDomain1);
        $this->em->flush();
        $leadDomain2 = $this->addNewLeadDomainModel($domain1);
        $leadDomain2->setContact($lead);
        $leadDomain2->setCount(4);
        $this->em->persist($leadDomain2);
        $this->em->flush();

        $company2 = $this->addNewCompany('Test Company 2 test.lfexample.com');

        $companyDomain1 = new FloodlightCompanyDomains();
        $companyDomain1->setCompany($company2);
        $companyDomain1->setDomain($domain2);
        $companyDomain1->setIsPrimaryDomain(true);
        $companyDomain1->setDateAdded(new \DateTime());
        $companyDomain1->setDateModified(new \DateTime());
        $this->em->persist($companyDomain1);
        $this->em->flush();

        $company3 = $this->addNewCompany('Test Company 3 lfexample.com');

        $companyDomain2 = new FloodlightCompanyDomains();
        $companyDomain2->setCompany($company3);
        $companyDomain2->setDomain($domain1);
        $companyDomain2->setIsPrimaryDomain(false);
        $companyDomain2->setDateAdded(new \DateTime());
        $companyDomain2->setDateModified(new \DateTime());
        $this->em->persist($companyDomain2);
        $this->em->flush();

        $floodlightLeadDomainsModel->recalculateLeadPrimaryCompany($lead);

        $leadModel = self::getContainer()->get('mautic.lead.model.lead');
        assert($leadModel instanceof \Mautic\LeadBundle\Model\LeadModel);
        $newLead   = $leadModel->getEntity($lead->getId());
        $this->assertInstanceOf(Lead::class, $newLead);
        $this->assertInstanceOf(Company::class, $newLead->getPrimaryCompany());
        $this->assertNotSame($company->getId(), $newLead->getPrimaryCompany()->getId());
        $this->assertSame($newLead->getPrimaryCompany(), $company2);
        $this->assertSame($newLead->getPrimaryCompany()->getName(), $company2->getName());
    }

    private function addNewCompany(string $name = 'Test Company'): Company
    {
        $company = new Company();
        $company->setName($name);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function addNewLeadDomainModel(string $domain = 'example.com'): FloodlightLeadDomains
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

        $newLeadDomain = new FloodlightLeadDomains();
        $newLeadDomain->setDomain($domain);
        $newLeadDomain->setContact($lead);
        $newLeadDomain->setIpHash('local');
        $newLeadDomain->setDateAdded(new \DateTime());
        $newLeadDomain->setDateModified(new \DateTime());
        $newLeadDomain->setCount(0);
        $this->em->persist($newLeadDomain);
        $this->em->flush();

        return $newLeadDomain;
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
