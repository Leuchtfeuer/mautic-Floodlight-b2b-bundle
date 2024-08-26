<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\IntegrationsBundle\Sync\Notification\Helper\UserHelper as NotificationUserHelper;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomainsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FloodlightCompanyDomainsModelTest extends MauticMysqlTestCase
{
    public function testSave(): void
    {
        $companyDomain = $this->loadModel();
        $this->markTestSkipped('This test is skipped because it is not implemented yet.');
        //        $model  = $this->loadModel();
        //        $domain = 'wienenergie.at';
        //        $lead = $this->createMock(\Mautic\LeadBundle\Entity\Lead::class);
        //        $details = [
        //            'company_name' => 'Wien Energie GmbH',
        //            'is_isp'       => null,
        //            'is_dialup'    => null,
        //        ];
    }

    private function loadModel(): FloodlightCompanyDomainsModel
    {
        $mockCompanyDomainRepository = $this->getMockBuilder(FloodlightCompanyDomainsRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $mockCompanyDomainRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn([]);

        $entityManager = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $entityManager->expects($this->any())
            ->method('getRepository')
            ->willReturn($mockCompanyDomainRepository);

        $security = $this->getMockBuilder(CorePermissions::class)
            ->disableOriginalConstructor()
            ->getMock();

        $userHelper = $this->getMockBuilder(UserHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $user = $this->em->getRepository(\Mautic\UserBundle\Entity\User::class)->find(1);

        $leadEntity = new \Mautic\LeadBundle\Entity\Lead();
        $leadEntity->setId(1);
        $leadEntity->setDateAdded(new \DateTime());
        $leadEntity->setDateModified(new \DateTime());
        $leadEntity->setIsPublished(true);
        $leadEntity->setCreatedBy($user);
        $leadEntity->setModifiedBy($user);
        $leadEntity->setOwner($user);
        //        $this->em->persist($leadEntity);

        $companyModelMock = $this->getMockBuilder(\Mautic\LeadBundle\Model\CompanyModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $companyEntity = new Company();
        $companyEntity->setName('Wien Energie GmbH');
        $companyEntity->setDateAdded(new \DateTime());
        $companyEntity->setDateModified(new \DateTime());
        $companyEntity->setIsPublished(true);
        $companyEntity->setCreatedBy($user);
        $companyEntity->setModifiedBy($user);
        $companyEntity->setOwner($user);
        $companyModelMock->expects($this->any())
            ->method('getEntity')
            ->willReturn($companyEntity);

        $leadMock = $this->getMockBuilder(\Mautic\LeadBundle\Model\LeadModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $leadMock->expects($this->once())
            ->method('getCompanies')
            ->willReturn([
                [
                    'company_id' => $companyEntity->getId(),
                ],
            ]);

        $notificationUserHelper = $this->getMockBuilder(NotificationUserHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notificationUserHelper->expects($this->once())
            ->method('getAdminUsers')
            ->willReturn([1]);

        $userModel = $this->getMockBuilder(\Mautic\UserBundle\Model\UserModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $userModel->expects($this->once())
            ->method('getEntity')
            ->willReturn($user);

        return new FloodlightCompanyDomainsModel(
            $entityManager,
            $security,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Translator::class),
            $userHelper,
            $this->createMock(LoggerInterface::class),
            $this->createMock(CoreParametersHelper::class),
            $companyModelMock,
            $leadMock,
            $notificationUserHelper,
            $userModel,
            $this->createMock(FloodlightLeadDomainsModel::class)
        );
    }
}
