<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;

class Status202Strategy
{
    public function __construct(
        private readonly FloodlightLeadDomainsModel $leadDomainsModel,
    ) {
    }

    /**
     * @param mixed[] $data
     *
     * @return array{
     *     status: string,
     *     domain: string|null,
     *     lookupType: string,
     *     details: mixed
     * }
     */
    public function prepareData(array $data): array
    {
        $floodlightDTO = new FloodlightDTO($data);

        $status           = FloodlightLeadDomains::STATUS_RETRY;
        $newFloodlightDTO = new FloodlightDTO([]);
        $newFloodlightDTO->setIpHash($floodlightDTO->getIpHash());
        $newFloodlightDTO->setDomains([]);
        $floodlightDTO = $newFloodlightDTO;

        return [
            'status'     => $status,
            'domain'     => null,
            'lookupType' => FloodlightLeadDomains::LOOKUP_TYPE_API,
            'details'    => [
                'floodlight'    => $floodlightDTO,
                'statusRequest' => $data['statusRequestCode'],
            ],
        ];
    }

    /**
     * @param array{
     *     status: string,
     *     domain: string|null,
     *     lookupType: string,
     *     details: mixed
     * } $processedData
     */
    public function process(array $processedData, Lead $lead): void
    {
        $leadDomain = $this->leadDomainsModel->addNewLeadDomainOrCountPlusOneInLeadDomain($lead, $processedData);
        $this->leadDomainsModel->setStatusToRetryLimitExceededIfTooHighCount($leadDomain);
    }
}
