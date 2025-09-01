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

        if ($isFloodlightAuditLog === true) {
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

    private function createSecureFloodlightAuditLog(array $logData): void
    {
        $ipAddress = $logData['ipAddress'] ?? '';
        if ($this->coreParametersHelper->get('anonymize_ip')) {
            $ipAddress = '*.*.*.*';
        }

        // 3. Sichere AuditLog-Erstellung
        $auditLog = new AuditLog();
        $auditLog->setBundle($logData['bundle']);
        $auditLog->setObject($logData['object']);
        $auditLog->setObjectId($logData['objectId']);
        $auditLog->setAction($logData['action']);
        $auditLog->setDetails($logData['details']);
        $auditLog->setIpAddress($ipAddress);
        $auditLog->setDateAdded(new \DateTime());
        $auditLog->setUserId(0);
        $auditLog->setUserName('Floodlight B2B');

        // 4. Speichern
        $this->auditLogModel->getRepository()->saveEntity($auditLog);

        // 5. Memory-Leak verhindern
        $this->em->detach($auditLog);
    }
}