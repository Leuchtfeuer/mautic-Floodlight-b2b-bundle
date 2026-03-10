<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Model\AuditLogModel;

class AuditLogHelper
{
    public function __construct(private AuditLogModel $auditLogModel)
    {
    }

    /**
     * @return array<string>
     */
    public function getNonFloodlightUpdatableFields(int $id): array
    {
        $result = $this->auditLogModel->getRepository()->findBy(
            ['object' => 'company', 'objectId' => $id],
        );
        $nonFloodlightAuditLogEntries = array_filter($result, function ($item) {
            return 'Floodlight B2B' !== $item->getUserName();
        });

        $fieldsNotUpdatableByFloodlight = [];

        foreach ($nonFloodlightAuditLogEntries as $entry) {
            $nonUpdatableFields             = array_key_exists('fields', $entry->getDetails()) ? array_keys($entry->getDetails()['fields']) : [];
            $nonUpdatableFields2            = array_keys($entry->getDetails());
            $fieldsNotUpdatableByFloodlight = array_merge($fieldsNotUpdatableByFloodlight, $nonUpdatableFields, $nonUpdatableFields2);
        }

        return array_unique($fieldsNotUpdatableByFloodlight);
    }
}
