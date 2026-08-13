<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Command;

use Doctrine\ORM\Exception\NotSupported;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command\LookupRetryCommand;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures\FixtureHelper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class EnrichCompanyCommandTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FixtureHelper $fixtureHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->fixtureHelper = new FixtureHelper($this->em, $this->client);
    }

    public function testEnrichByDomainWhenNoCompaniesInDatabase(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->assertStringContainsString('No companies to enrich', $commandTester->getDisplay());
    }

    public function testEnrichByDomainWhenPluginNotPublished(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(false);
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->assertStringContainsString('Plugin is not published', $commandTester->getDisplay());
    }

    public function testEnrichByDomainWhenNoApiKeySet(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(true, '', '');
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->assertStringContainsString('There is no API key set for the plugin', $commandTester->getDisplay());
    }

    public function testEnrichByDomainWhenInvalidApiKey(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(true, 'invalidAccessKeyId', 'invalidSecretKey');
        $company       = $this->addCompany('Test Company');
        $this->addCompanyDomain('leuchtfeuer.com', $company);
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->assertStringContainsString('You are using an invalid API key', $commandTester->getDisplay());
    }

    /**
     * @throws NotSupported
     */
    public function testEnrichByDomainStatus200(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company       = $this->addCompany('Leuchtfeuer');
        $this->addCompanyDomain('leuchtfeuer.com', $company);

        // First 200 command --> company should be updated
        $commandTester     = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $newCompanyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy([], ['company' => 'ASC', 'domain' => 'ASC']);
        $companies         = $this->em->getRepository(Company::class)->findAll();
        $this->assertCount(1, $newCompanyDomains);
        $this->assertCount(1, $companies);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $newCompanyDomains[0]);
        $this->assertNotNull($company->getFieldValue('f4b_enrichment_date'));
        $this->assertNotSame(LookupRetryCommand::DATE_DEFAULT, $company->getFieldValue('f4b_enrichment_date'));
        $newCompany = $newCompanyDomains[0]->getCompany();
        $this->assertSame('Hanover', $newCompany->getCity());
        $this->assertStringContainsString('Company data updated Leuchtfeuer', $commandTester->getDisplay());
        $this->assertStringContainsString('CompanyDomain leuchtfeuer.com data updated', $commandTester->getDisplay());
        $this->assertStringContainsString('Enriching company data DONE!', $commandTester->getDisplay());

        // Second 200 command --> company should not be updated again
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->assertStringContainsString('No companies to enrich', $commandTester->getDisplay());
    }

    /**
     * @throws NotSupported
     */
    public function testNewCompanyDomainAddedForCompanyThatExistedBeforeFloodlight(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $this->addCompany('Test Company', '202domain.com');

        $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $newCompanyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy([], ['company' => 'ASC', 'domain' => 'ASC']);
        $this->assertCount(1, $newCompanyDomains);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $newCompanyDomains[0]);
        $this->assertSame('202domain.com', $newCompanyDomains[0]->getDomain());
    }

    public function testPreviousValuesDontGetOverriddenViaEnrichment(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $this->addCompany('Leuchtfeuer', 'leuchtfeuer.com');
        $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $companies         = $this->em->getRepository(Company::class)->findAll();
        $this->assertCount(1, $companies);
        // Leuchtfeuer doesn't get overridden by Leuchtfeuer Digital Marketing GmbH
        $this->assertSame('Leuchtfeuer', $companies[0]->getName());
        // The other data gets enriched properly
        $this->assertSame('Hanover', $companies[0]->getCity());
    }

    /**
     * @throws NotSupported
     */
    public function testEnrichByDomainStatus202(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $this->addCompany('Test Company', '202domain.com');

        $kernel        = self::getContainer()->get(KernelInterface::class);
        $application   = new Application($kernel);
        $command       = $application->find('leuchtfeuer:floodlight:enrich-companies');
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        $this->assertStringContainsString('Company domain 202domain.com with request 202, enrichment date set to default date', $commandTester->getDisplay());
        $this->assertStringContainsString('1 company still pending enrichment results.', $commandTester->getDisplay());

        // Second 202 command, enrichment date should be set to current date
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);
        $this->assertStringContainsString('Lead domain 202domain.com with request 202, enrichment date set to current date', $commandTester->getDisplay());
        $this->assertStringContainsString('1 company still pending enrichment results.', $commandTester->getDisplay());

        // Third 202 command, there shouldn't be found a company to enrich
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);
        $this->assertStringContainsString('No companies to enrich', $commandTester->getDisplay());
    }

    /**
     * @throws NotSupported
     */
    public function testEnrichByDomainStatus404(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $company       = $this->addCompany('Test Company', 'https://localhostdomain1245.com');
        $this->addCompanyDomain('localhostdomain1245.com', $company);

        // First 404 command, enrichment date should be set to current date
        $commandTester     = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $newCompanyDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findBy([], ['company' => 'ASC', 'domain' => 'ASC']);
        $companies         = $this->em->getRepository(Company::class)->findAll();
        $this->assertCount(1, $newCompanyDomains);
        $this->assertCount(1, $companies);
        $this->assertNotNull($company->getFieldValue('f4b_enrichment_date'));
        $this->assertNotSame(LookupRetryCommand::DATE_DEFAULT, $company->getFieldValue('f4b_enrichment_date'));
        $this->assertSame('Test Company', $companies[0]->getName());
        $this->assertStringContainsString('Enriching company data DONE!', $commandTester->getDisplay());

        // Second 404 command, there shouldn't be found a company to enrich
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->assertStringContainsString('No companies to enrich', $commandTester->getDisplay());
    }

    public function testEnrichTelcoCompanyWhenIgnoreTelcoSetSettingsSetToFalse(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(true);
        $this->updateIgnoreTelcoSettings(false);
        $company       = $this->addCompany('Telco', 'telcodomain.com');
        $this->addCompanyDomain('telcodomain.com', $company);
        $this->createUnknownLeadAndAddToCompany($company);
        $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);
    }

    public function testEnrichTelcoCompanyWithKnownLead(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $this->updateIgnoreTelcoSettings(true);
        $company       = $this->addCompany('Telco', 'telcodomain.com');
        $this->addCompanyDomain('telcodomain.com', $company);
        $this->createKnownLeadAndAddToCompany($company);
        $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);
    }

    public function testEnrichNonTelcoCompany(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $this->updateIgnoreTelcoSettings(true);
        $company       = $this->addCompany('Leuchtfeuer');
        $this->addCompanyDomain('leuchtfeuer.com', $company);
        $this->createUnknownLeadAndAddToCompany($company);
        $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->checkNumberOfLeadsAssignedToCompany(1);
        $this->checkIfCompanyDomainHasIsTelcoValue(false);
    }

    public function testEnrichTelcoCompany(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $this->updateIgnoreTelcoSettings(true);
        $company       = $this->addCompany('Telco', 'telcodomain.com');
        $this->addCompanyDomain('telcodomain.com', $company);
        $this->createUnknownLeadAndAddToCompany($company);
        $this->testSymfonyCommand('leuchtfeuer:floodlight:enrich-companies');
        $this->checkNumberOfLeadsAssignedToCompany(0);
        $this->checkIfCompanyDomainHasIsTelcoValue(true);
    }

    private function checkIfCompanyDomainHasIsTelcoValue(?bool $telcoValue): void
    {
        $companyDomainsHit = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertNotEmpty($companyDomainsHit);
        $this->assertCount(1, $companyDomainsHit);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $companyDomainsHit[0]);
        $this->assertSame($telcoValue, $companyDomainsHit[0]->getIsTelco());
    }

    private function addCompany(string $name='Test Company', string $website = 'https://leuchtfeuer.com/marketing-technologie/'): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());
        $company->setWebsite($website);
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->saveEntity($company);

        return $company;
    }

    private function addCompanyDomain(string $domain, Company $company): FloodlightCompanyDomains
    {
        $companyDomain = new FloodlightCompanyDomains();
        $companyDomain->setDomain($domain);
        $companyDomain->setCompany($company);
        $companyDomain->setIsPrimaryDomain(true);
        $companyDomain->setDateAdded(new \DateTime());
        $companyDomain->setDateModified(new \DateTime());
        $this->em->persist($companyDomain);
        $this->em->flush();

        return $companyDomain;
    }

    private function checkNumberOfLeadsAssignedToCompany(int $count): void
    {
        $companyLeads          = $this->em->getRepository(CompanyLead::class)->findAll();
        $this->assertCount($count, $companyLeads);
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

    private function createKnownLeadAndAddToCompany(Company $company): void
    {
        $lead         = $this->createKnownLead();
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->addLeadToCompany($company, $lead);
        $companyModel->saveEntity($company);
    }

    private function createUnknownLeadAndAddToCompany(Company $company): void
    {
        $lead         = $this->createAnonymousLead();
        $companyModel = self::getContainer()->get(CompanyModel::class);
        $companyModel->addLeadToCompany($company, $lead);
        $companyModel->saveEntity($company);
    }

    private function createKnownLead(): Lead
    {
        $lead = new Lead();
        $lead->setEmail('test@test.com');
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    private function createAnonymousLead(): Lead
    {
        $lead = new Lead();
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
