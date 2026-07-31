<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

final class FieldDefinition
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $dbAlias,
        public readonly string $dtoProperty,
        public readonly FieldType $type,
        public readonly CompanyMappingType $companyMapping,
        public readonly ?string $companySetter = null,
        public readonly bool $truncate = true,
    ) {
    }
}
