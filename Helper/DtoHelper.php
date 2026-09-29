<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightFieldRegistry;

class DtoHelper
{
    public function __construct(
        private readonly AuditLogHelper $auditLogHelper,
    ) {
    }

    public function cleanDtoFromNonFloodlightUpdatableFields(FloodlightDTO $floodlightDTO, Company $company): FloodlightDTO
    {
        $nonUpdatableFields = $this->auditLogHelper->getNonFloodlightUpdatableFields($company->getId());
        $reflectionClass    = new \ReflectionClass($floodlightDTO);
        foreach ($nonUpdatableFields as $field) {
            $def         = FloodlightFieldRegistry::getByDbAlias($field);
            $dtoProperty = $def ? $def->dtoProperty : $field;
            if (property_exists($floodlightDTO, $dtoProperty)) {
                $setterName   = 'set'.ucfirst($dtoProperty);
                $defaultValue = $this->getDefaultValueForProperty($reflectionClass, $dtoProperty);
                $floodlightDTO->$setterName($defaultValue);
            }
        }

        return $floodlightDTO;
    }

    private function getDefaultValueForProperty(\ReflectionClass $reflectionClass, string $propertyName): mixed
    {
        try {
            $property = $reflectionClass->getProperty($propertyName);
            $type     = $property->getType();

            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();

                // Handle nullable types
                if ($type->allowsNull() && 'int' === $typeName) {
                    return null;
                }

                return match ($typeName) {
                    'string' => '',
                    'int'    => null,
                    'array'  => [],
                    default  => null,
                };
            }
        } catch (\ReflectionException) {
        }

        return '';
    }
}
