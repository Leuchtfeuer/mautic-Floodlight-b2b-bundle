<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

enum CompanyMappingType
{
    case DIRECT_SETTER;
    case ADD_UPDATED_FIELD;
    case DIRECT_SETTER_AND_UPDATED_FIELD;
    case NONE;
}
