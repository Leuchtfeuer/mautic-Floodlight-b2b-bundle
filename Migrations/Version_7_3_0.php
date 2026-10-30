<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_7_3_0 extends AbstractMigration
{
    private const FIELD_ALIAS = 'companylogo_filename';

    private const OLD_GROUP = 'professional';

    private const NEW_GROUP = 'core';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            if (!$schema->hasTable($this->concatPrefix('lead_fields'))) {
                return false;
            }
        } catch (SchemaException) {
            return false;
        }

        $count = $this->entityManager->getConnection()->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE alias = :alias AND object = :object AND field_group = :group', $this->concatPrefix('lead_fields')),
            ['alias' => self::FIELD_ALIAS, 'object' => 'company', 'group' => self::OLD_GROUP]
        );

        return $count > 0;
    }

    protected function up(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            sprintf('UPDATE %s SET field_group = :newGroup WHERE alias = :alias AND object = :object AND field_group = :oldGroup', $this->concatPrefix('lead_fields')),
            [
                'newGroup' => self::NEW_GROUP,
                'alias'    => self::FIELD_ALIAS,
                'object'   => 'company',
                'oldGroup' => self::OLD_GROUP,
            ]
        );
    }
}
