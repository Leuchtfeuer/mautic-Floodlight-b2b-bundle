<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CompanyDescriptionFieldMigrator;

class Version_5_11_0 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            if (!$schema->hasTable($this->concatPrefix('lead_fields'))) {
                return false;
            }
            if (!$schema->hasTable($this->concatPrefix('companies'))) {
                return false;
            }
        } catch (SchemaException) {
            return false;
        }

        return $this->getMigrator()->hasPendingMigration();
    }

    protected function up(): void
    {
        $this->getMigrator()->migrate();
    }

    private function getMigrator(): CompanyDescriptionFieldMigrator
    {
        return new CompanyDescriptionFieldMigrator($this->entityManager->getConnection());
    }
}
