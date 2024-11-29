<?php

namespace LeuchtfeuerFloodlightB2BBundle\Tests\Functional\Model;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Facade\ReloadFacade;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class FloodlightLeadDomainsModelTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
    }

    public function testGetEntity(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains');
        assert($floodlightLeadDomainsModel instanceof FloodlightLeadDomainsModel);
        $leadDomain       = $this->addNewLeadDomainModel();
        $leadDomainId     = $leadDomain->getId();
        $leadDomainEntity = $floodlightLeadDomainsModel->getEntity($leadDomainId);
        $this->assertInstanceOf(FloodlightLeadDomains::class, $leadDomainEntity);
        $this->assertEquals($leadDomainId, $leadDomainEntity->getId());
        $this->assertInstanceOf(Lead::class, $leadDomainEntity->getContact());
    }

    public function testIsBlackListed(): void
    {
        $floodlightLeadDomainsModel = self::getContainer()->get('mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains');
        assert($floodlightLeadDomainsModel instanceof FloodlightLeadDomainsModel);
        $domain     = 'lfexample.com';
        $leadDomain = $this->addNewLeadDomainModel($domain);
        $domain     = $leadDomain->getDomain();

        $this->assertFalse($floodlightLeadDomainsModel->isBlackListed($domain));

        (new DomainBlacklistHelper())->add($domain);
        $this->assertTrue($floodlightLeadDomainsModel->isBlackListed($domain));

        $leadDomainTwo = $this->addNewLeadDomainModel('test.lfexample.com');
        $domainTwo     = $leadDomainTwo->getDomain();
        $this->assertTrue($floodlightLeadDomainsModel->isBlackListed($domainTwo));
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

    public function beforeTearDown(): void
    {
        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        if (file_exists($configPathTo)) {
            unlink($configPathTo);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws RandomException
     * @throws ORMException
     */
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
        $newLeadDomain->setDateAdded(new \DateTime());
        $newLeadDomain->setDateModified(new \DateTime());
        $newLeadDomain->setCount(0);
        $this->em->persist($newLeadDomain);
        $this->em->flush();

        return $newLeadDomain;
    }
}
