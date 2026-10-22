<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Model;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures\FixtureHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;

/**
 * @group no-ci
 */
class FloodlightCompanyDomainsModelTest extends MauticMysqlTestCase
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
}
