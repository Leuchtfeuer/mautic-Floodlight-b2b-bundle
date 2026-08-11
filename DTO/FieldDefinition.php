<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

final readonly class FieldDefinition
{
    public function __construct(
        public string $apiKey,
        public string $dbAlias,
        public string $dtoProperty,
        public FieldType $type,
        public CompanyMappingType $companyMapping,
        public ?string $companySetter = null,
        public bool $truncate = true,
    ) {
    }
}
