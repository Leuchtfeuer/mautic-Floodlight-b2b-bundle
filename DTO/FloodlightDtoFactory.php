<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility;

class FloodlightDtoFactory
{
    public function __construct(
        private FieldModel $fieldModel,
        private DomainUtility $domainUtility,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): FloodlightDTO
    {
        return new FloodlightDTO($data, $this->fieldModel, $this->domainUtility);
    }
}
