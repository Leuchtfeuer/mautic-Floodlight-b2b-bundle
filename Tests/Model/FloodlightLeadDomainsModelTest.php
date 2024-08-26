<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Model;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FloodlightLeadDomainsModelTest extends MauticMysqlTestCase
{
    public function testGetEntity(): void
    {
        $model  = $this->loadLeadDomainsModel();
        $entity = $model->getEntity();
        $this->assertInstanceOf(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains::class, $entity);
    }

    private function loadLeadDomainsModel(): FloodlightLeadDomainsModel
    {
        $entityManager = $this
            ->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $security = $this->getMockBuilder(CorePermissions::class)
            ->disableOriginalConstructor()
            ->getMock();

        $userHelper = $this->getMockBuilder(UserHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new FloodlightLeadDomainsModel(
            $entityManager,
            $security,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Translator::class),
            $userHelper,
            $this->createMock(LoggerInterface::class),
            $this->createMock(CoreParametersHelper::class),
            $this->createMock(\Mautic\LeadBundle\Model\LeadModel::class),
            $this->createMock(\Mautic\LeadBundle\Model\CompanyModel::class),
        );
    }

    public function testCheckWhoisCustomField(): void
    {
        $this->markTestSkipped('This test is skipped because it is not implemented yet.');
    }
}
