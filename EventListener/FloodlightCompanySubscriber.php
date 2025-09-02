<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Entity\AuditLog;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FloodlightCompanySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IpLookupHelper $ipLookupHelper,
        private AuditLogModel $auditLogModel,
        private CoreParametersHelper $coreParametersHelper,
        private EntityManagerInterface $em
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COMPANY_POST_SAVE => ['onCompanyPostSave', 100],
        ];
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        $company = $event->getCompany();
        $details = $event->getChanges();

        if (!$details) {
            return;
        }

        $isFloodlightAuditLog = $company->getEventData('floodlightAuditLogMarker');

        if (true === $isFloodlightAuditLog) {
            $log = [
                'bundle'    => 'lead',
                'object'    => 'company',
                'objectId'  => $company->getId(),
                'action'    => ($event->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
            ];

            $this->createSecureFloodlightAuditLog($log);
            $event->stopPropagation();
        }
    }

    /**
     * @param array<mixed> $logData
     */
    private function createSecureFloodlightAuditLog(array $logData): void
    {
        $ipAddress = $logData['ipAddress'] ?? '';
        if ($this->coreParametersHelper->get('anonymize_ip')) {
            $ipAddress = '*.*.*.*';
        }

        $auditLog = new AuditLog();
        assert(is_string($logData['bundle']));
        $auditLog->setBundle($logData['bundle']);
        assert(is_string($logData['object']));
        $auditLog->setObject($logData['object']);
        assert(is_int($logData['objectId']));
        $auditLog->setObjectId($logData['objectId']);
        assert(is_string($logData['action']));
        $auditLog->setAction($logData['action']);
        assert(is_array($logData['details']));
        $auditLog->setDetails($logData['details']);
        assert(is_string($ipAddress));
        $auditLog->setIpAddress((string) $ipAddress);
        $auditLog->setDateAdded(new \DateTime());
        $auditLog->setUserId(0);
        $auditLog->setUserName('Floodlight B2B');

        $this->auditLogModel->getRepository()->saveEntity($auditLog);

        $this->em->detach($auditLog);
    }
}
