<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

enum FieldType
{
    case STRING;
    case INT;
    case ARRAY;
    case LIST_VALUE;
    case LIST_ARRAY;
}
