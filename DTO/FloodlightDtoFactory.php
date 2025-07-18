<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use Mautic\LeadBundle\Model\FieldModel;

class FloodlightDtoFactory
{
    public function __construct(private FieldModel $fieldModel)
    {
    }

    public function create(array $data): FloodlightDTO
    {
        return new FloodlightDTO($data, $this->fieldModel);
    }
}