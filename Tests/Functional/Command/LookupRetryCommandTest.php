<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Command;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\fixtures\FixtureHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;

/**
 * @group no-ci
 */
class LookupRetryCommandTest extends MauticMysqlTestCase
{
    use RebuildsCompanyTableAfterCustomFieldCleanup;

    protected $useCleanupRollback = false;

    private FixtureHelper $fixtureHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->fixtureHelper = new FixtureHelper($this->em, $this->client);
    }

    public function testEnrichWhenPluginNotPublished(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(false);
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals('', $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_RETRY, $newLeadDomains[0]->getStatus());
        $this->assertEquals(1, $newLeadDomains[0]->getCount());
    }

    public function testEnrichWhenApiKeyNotSet(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(true, null, null);
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals('', $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_RETRY, $newLeadDomains[0]->getStatus());
        $this->assertEquals(1, $newLeadDomains[0]->getCount());
    }

    public function testEnrichIfInvalidApiKey(): void
    {
        $this->fixtureHelper->createAndEnablePlugin(true, 'invalidAccessKeyId', 'invalidSecretKey');
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals('', $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_RETRY, $newLeadDomains[0]->getStatus());
        $this->assertEquals(1, $newLeadDomains[0]->getCount());
    }

    public function testEnrichRetryEntryToSuccessEntry(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals('leuchtfeuer.com', $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_SUCCESS, $newLeadDomains[0]->getStatus());
        $this->assertEquals(1, $newLeadDomains[0]->getCount());
        $this->assertEquals(FloodlightLeadDomains::LOOKUP_TYPE_API, $newLeadDomains[0]->getLookupType());

        $newCompanies = $this->em->getRepository(Company::class)->findAll();
        $this->assertCount(1, $newCompanies);
        $this->assertInstanceOf(Company::class, $newCompanies[0]);
        $this->assertSame('Leuchtfeuer Digital Marketing GmbH', $newCompanies[0]->getName());

        $newLeadCompaniesDomains = $this->em->getRepository(FloodlightCompanyDomains::class)->findAll();
        $this->assertCount(1, $newLeadCompaniesDomains);
        $this->assertInstanceOf(FloodlightCompanyDomains::class, $newLeadCompaniesDomains[0]);
        $this->assertSame('leuchtfeuer.com', $newLeadCompaniesDomains[0]->getDomain());
    }

    public function testEnrichRetryEntryTo404Entry(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, '404hash', 1, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals(null, $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_NO_DATA_FOUND, $newLeadDomains[0]->getStatus());
        $this->assertEquals(1, $newLeadDomains[0]->getCount());
    }

    public function testEnrichRetryEntryWhenFloodlightGivesAnother202Response(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, 'retryIpHash', 1, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals(null, $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_RETRY, $newLeadDomains[0]->getStatus());
        $this->assertEquals(2, $newLeadDomains[0]->getCount());
        $this->assertEquals(FloodlightLeadDomains::LOOKUP_TYPE_API, $newLeadDomains[0]->getLookupType());
    }

    public function testEnrichRetryEntryAndExceedRetryLimit(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, 'retryIpHash', 2, FloodlightLeadDomains::STATUS_RETRY);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals(null, $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_RETRY_LIMIT_EXCEEDED, $newLeadDomains[0]->getStatus());
        $this->assertEquals(3, $newLeadDomains[0]->getCount());
    }

    /**
     * Description: This test checks the scenario where one success entry and one retry entry exist for the same IP hash.
     * When then for the retry we get a success response from the floodlight API, the retry entry should be deleted and the count of the success entry should be increased.
     * */
    public function testOneSuccessEntryAndOneRetryEntry(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $lead              = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain   = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_RETRY);
        $successLeadDomain = $this->addNewLeadDomain($lead, '70d96c1eae48442fe5fc5a7f234f3d0f13d3da42', 1, FloodlightLeadDomains::STATUS_SUCCESS);
        $successLeadDomain->setDomain('leuchtfeuer.com');
        $this->em->persist($successLeadDomain);
        $this->em->flush();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals('leuchtfeuer.com', $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_SUCCESS, $newLeadDomains[0]->getStatus());
        $this->assertEquals(2, $newLeadDomains[0]->getCount());
        $this->assertEquals(FloodlightLeadDomains::LOOKUP_TYPE_API, $newLeadDomains[0]->getLookupType());
    }

    /**
     * Description: This test checks the scenario where one 404 entry and one retry entry exist for the same IP hash.
     * When then for the retry we get a 404 response from the floodlight API, the retry entry should be deleted and the count of the 404 entry should be increased.
     * */
    public function testOne404EntryAndOneRetryEntry(): void
    {
        $this->fixtureHelper->createAndEnablePlugin();
        $lead            = $this->addLead('Test Lead', 'test11@example.com');
        $retryLeadDomain = $this->addNewLeadDomain($lead, '404hash', 1, FloodlightLeadDomains::STATUS_RETRY);
        $LeadDomain404   = $this->addNewLeadDomain($lead, '404hash', 1, FloodlightLeadDomains::STATUS_NO_DATA_FOUND);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:floodlight:lookup-retries');

        $newLeadDomains = $this->em->getRepository(FloodlightLeadDomains::class)->findAll();
        $this->assertCount(1, $newLeadDomains);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $newLeadDomains[0]);
        $this->assertEquals(null, $newLeadDomains[0]->getDomain());
        $this->assertEquals(FloodlightLeadDomains::STATUS_NO_DATA_FOUND, $newLeadDomains[0]->getStatus());
        $this->assertEquals(2, $newLeadDomains[0]->getCount());
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

    private function addLead(string $name='Test Lead', string $email='test@example.com'): Lead
    {
        $lead = new Lead();
        $lead->setFirstName($name);
        $lead->setEmail($email);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
