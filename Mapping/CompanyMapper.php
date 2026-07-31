<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Mapping;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\CompanyMappingType;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightFieldRegistry;

class CompanyMapper
{
    public function mapToCompany(FloodlightDTO $dto, Company $company): Company
    {
        foreach (FloodlightFieldRegistry::getDefinitions() as $def) {
            if (CompanyMappingType::NONE === $def->companyMapping) {
                continue;
            }

            $getter = 'get'.ucfirst($def->dtoProperty);
            $value  = $dto->$getter();

            if (empty($value)) {
                continue;
            }

            if (CompanyMappingType::DIRECT_SETTER === $def->companyMapping
                || CompanyMappingType::DIRECT_SETTER_AND_UPDATED_FIELD === $def->companyMapping) {
                $company->{$def->companySetter}($value);
            }

            if (CompanyMappingType::ADD_UPDATED_FIELD === $def->companyMapping
                || CompanyMappingType::DIRECT_SETTER_AND_UPDATED_FIELD === $def->companyMapping) {
                $company->addUpdatedField($def->dbAlias, $value);
            }
        }

        if (200 === $dto->getStatusHttpRequest()) {
            $company->addUpdatedField('f4b_enrichment_date', (new \DateTime())->format('Y-m-d H:i:s'));
        }

        $company->setEventData('floodlightAuditLogMarker', true);

        return $company;
    }
}
