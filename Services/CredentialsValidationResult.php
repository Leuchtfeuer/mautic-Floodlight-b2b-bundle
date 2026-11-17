<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

enum CredentialsValidationResult
{
    case Valid;
    case Invalid;
    case Unverifiable;
}
