<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Model\FieldModel;

class FloodlightDtoFactory
{
    public function __construct(private FieldModel $fieldModel)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): FloodlightDTO
    {
        return new FloodlightDTO($data, $this->fieldModel);
    }
}
