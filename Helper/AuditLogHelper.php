<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Model\AuditLogModel;

class AuditLogHelper
{
    public function __construct(private AuditLogModel $auditLogModel)
    {
    }


    public function getNonFloodlightUpdatableFields(int $id): void
    {
        $result = $this->auditLogModel->getRepository()->findBy(
            ['object' => 'company', 'objectId' => $id],
        );
        $nonFloodlightAuditLogEntries = array_filter($result, function($item) {
            return $item->getUserName() !== 'Floodlight B2B';
        });

        $fieldsNotUpdatableByFloodlight = [];

        foreach ($nonFloodlightAuditLogEntries as $entry) {
            $details = array_keys($entry->getDetails()['fields']);
            $details2 = $details;
        }
        $result2 = $result;
    }
}
