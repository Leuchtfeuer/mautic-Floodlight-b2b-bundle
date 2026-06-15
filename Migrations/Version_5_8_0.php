<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CompanyFieldValueSanitizer;

class Version_5_8_0 extends AbstractMigration
{
    private static ?CompanyFieldValueSanitizer $sanitizer = null;

    public static function setSanitizer(CompanyFieldValueSanitizer $sanitizer): void
    {
        self::$sanitizer = $sanitizer;
    }

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

        $sanitizer = $this->getSanitizer();

        return $sanitizer->hasPendingSanitization();
    }

    protected function up(): void
    {
        $sanitizer = $this->getSanitizer();
        $sanitizer->sanitize();
    }

    private function getSanitizer(): CompanyFieldValueSanitizer
    {
        if (null === self::$sanitizer) {
            throw new \RuntimeException('CompanyFieldValueSanitizer must be set before running Version_5_8_0 migration.');
        }

        return self::$sanitizer;
    }
}
