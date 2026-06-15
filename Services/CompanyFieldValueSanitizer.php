<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Doctrine\DBAL\Connection;

class CompanyFieldValueSanitizer
{
    private const FIELDS_TO_CHECK = [
        'companyindustry',
        'f4b_companyindustry_list',
        'f4b_companylabels',
        'f4b_companyindustry_icb_industrygroup',
        'f4b_companyindustry_icb_industry',
        'f4b_companyindustry_icb_subIndustry',
        'f4b_companyindustry_icb_sector',
        'f4b_companyemployees_range',
        'f4b_companyannual_revenue_range',
        'f4b_companytechnologies',
        'f4b_companytype',
    ];

    private string $tablePrefix;

    public function __construct(
        private Connection $connection,
    ) {
        $this->tablePrefix = defined('MAUTIC_TABLE_PREFIX') && is_string(MAUTIC_TABLE_PREFIX) ? MAUTIC_TABLE_PREFIX : '';
    }

    public function hasPendingSanitization(): bool
    {
        $leadFieldsTable = $this->tablePrefix.'lead_fields';

        foreach (self::FIELDS_TO_CHECK as $fieldAlias) {
            $sql = "SELECT properties FROM {$leadFieldsTable}
                    WHERE alias = :alias AND object = 'company' LIMIT 1";

            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue('alias', $fieldAlias);
            $result = $stmt->executeQuery();
            $row    = $result->fetchAssociative();

            if ($row && !empty($row['properties'])) {
                $properties = unserialize($row['properties']);

                if (is_array($properties) && isset($properties['list']) && is_array($properties['list'])) {
                    foreach ($properties['list'] as $item) {
                        if (self::needsSanitization($item['value'] ?? '') || self::needsSanitization($item['label'] ?? '')) {
                            return true;
                        }
                    }
                }
            }
        }

        return $this->hasCompaniesNeedingNormalization();
    }

    private function hasCompaniesNeedingNormalization(): bool
    {
        $companiesTable = $this->tablePrefix.'companies';

        foreach (self::FIELDS_TO_CHECK as $fieldAlias) {
            $checkSql = "SHOW COLUMNS FROM {$companiesTable} LIKE :fieldAlias";
            $stmt     = $this->connection->prepare($checkSql);
            $stmt->bindValue('fieldAlias', $fieldAlias);
            $result   = $stmt->executeQuery();

            if (!$result->fetchAssociative()) {
                continue;
            }

            $sql    = "SELECT 1 FROM {$companiesTable} WHERE {$fieldAlias} LIKE '% %' LIMIT 1";
            $result = $this->connection->executeQuery($sql);

            if ($result->fetchAssociative()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{fields_updated: int, companies_updated: int, duplicates_removed: int}
     */
    public function sanitize(): array
    {
        $leadFieldsTable = $this->tablePrefix.'lead_fields';
        $companiesTable  = $this->tablePrefix.'companies';

        $stats = [
            'fields_updated'      => 0,
            'companies_updated'   => 0,
            'duplicates_removed'  => 0,
        ];

        foreach (self::FIELDS_TO_CHECK as $fieldAlias) {
            $fieldStats = $this->sanitizeField($leadFieldsTable, $companiesTable, $fieldAlias);
            $stats['fields_updated'] += $fieldStats['field_updated'] ? 1 : 0;
            $stats['companies_updated'] += $fieldStats['companies_updated'];
            $stats['duplicates_removed'] += $fieldStats['duplicates_removed'];
        }

        return $stats;
    }

    /**
     * @return array{field_updated: bool, companies_updated: int, duplicates_removed: int}
     */
    private function sanitizeField(string $leadFieldsTable, string $companiesTable, string $fieldAlias): array
    {
        $stats = [
            'field_updated'       => false,
            'companies_updated'   => 0,
            'duplicates_removed'  => 0,
        ];

        $fieldData = $this->getFieldProperties($leadFieldsTable, $fieldAlias);
        if (!$fieldData) {
            return $stats;
        }

        ['fieldId' => $fieldId, 'properties' => $properties] = $fieldData;

        // Step 1: Decode corrupted field list values (e.g., "Software &#38;#38; Computer" → "Software & Computer")
        $sanitizeResult              = $this->sanitizeAndDeduplicateProperties($properties['list']);
        $stats['duplicates_removed'] = $sanitizeResult['duplicates_removed'];

        // Step 2: Update field definition with cleaned values
        if ($this->updateFieldProperties($leadFieldsTable, $fieldId, $properties, $sanitizeResult['cleanProperties'])) {
            $stats['field_updated'] = true;
        }

        // Step 3: Fix legacy company values to match field list format
        // Floodlight used to save "Software & Computer Services" but field list expects "Software-&-Computer-Services"
        $stats['companies_updated'] = $this->normalizeCompanyValues($companiesTable, $fieldAlias);

        return $stats;
    }

    /**
     * @return array{fieldId: int, properties: array<string, mixed>}|null
     */
    private function getFieldProperties(string $leadFieldsTable, string $fieldAlias): ?array
    {
        $sql = "SELECT id, properties FROM {$leadFieldsTable}
                WHERE alias = :alias AND object = 'company' LIMIT 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('alias', $fieldAlias);
        $result = $stmt->executeQuery();
        $row    = $result->fetchAssociative();

        if (!$row || empty($row['properties'])) {
            return null;
        }

        $properties = unserialize($row['properties']);

        if (!is_array($properties) || !isset($properties['list']) || !is_array($properties['list'])) {
            return null;
        }

        return [
            'fieldId'    => (int) $row['id'],
            'properties' => $properties,
        ];
    }

    /**
     * @param array<array<string, string>> $listItems
     *
     * @return array{cleanProperties: array<array<string, string>>, duplicates_removed: int}
     */
    private function sanitizeAndDeduplicateProperties(array $listItems): array
    {
        $seenClean         = [];
        $cleanProperties   = [];
        $duplicatesRemoved = 0;

        foreach ($listItems as $item) {
            if (!isset($item['value']) || !isset($item['label'])) {
                continue;
            }

            $newValue = self::sanitizeHtmlEntities($item['value']);
            $newLabel = self::sanitizeHtmlEntities($item['label']);

            if (!isset($seenClean[$newValue])) {
                $cleanProperties[]    = ['label' => $newLabel, 'value' => $newValue];
                $seenClean[$newValue] = true;
            } else {
                ++$duplicatesRemoved;
            }
        }

        return [
            'cleanProperties'    => $cleanProperties,
            'duplicates_removed' => $duplicatesRemoved,
        ];
    }

    /**
     * @param array<string, mixed>         $properties
     * @param array<array<string, string>> $cleanProperties
     */
    private function updateFieldProperties(string $leadFieldsTable, int $fieldId, array $properties, array $cleanProperties): bool
    {
        if ($cleanProperties === $properties['list']) {
            return false;
        }

        $properties['list']   = $cleanProperties;
        $serializedProperties = serialize($properties);

        $updateFieldSql = "UPDATE {$leadFieldsTable} SET properties = :properties WHERE id = :id";
        $stmt           = $this->connection->prepare($updateFieldSql);
        $stmt->bindValue('properties', $serializedProperties);
        $stmt->bindValue('id', $fieldId);
        $stmt->executeStatement();

        return true;
    }

    private function normalizeCompanyValues(string $companiesTable, string $fieldAlias): int
    {
        $checkSql = "SHOW COLUMNS FROM {$companiesTable} LIKE :fieldAlias";
        $stmt     = $this->connection->prepare($checkSql);
        $stmt->bindValue('fieldAlias', $fieldAlias);
        $result = $stmt->executeQuery();

        if (!$result->fetchAssociative()) {
            return 0;
        }

        $sql = "SELECT id, {$fieldAlias} FROM {$companiesTable}
                WHERE {$fieldAlias} IS NOT NULL AND {$fieldAlias} != ''";

        $result    = $this->connection->executeQuery($sql);
        $companies = $result->fetchAllAssociative();

        $updateGroups = [];
        foreach ($companies as $company) {
            $currentValue = $company[$fieldAlias];
            $newValue     = $this->normalizeValue($currentValue);

            if ($newValue !== $currentValue) {
                $updateGroups[$newValue][] = $company['id'];
            }
        }

        $updated = 0;
        foreach ($updateGroups as $newValue => $ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->connection->executeStatement(
                "UPDATE {$companiesTable} SET {$fieldAlias} = ? WHERE id IN ({$placeholders})",
                array_merge([$newValue], $ids)
            );
            $updated += count($ids);
        }

        return $updated;
    }

    private function normalizeValue(string $value): string
    {
        if (str_contains($value, '|')) {
            $values           = explode('|', $value);
            $normalizedValues = array_map(fn ($v) => str_replace(' ', '-', $v), $values);

            return implode('|', $normalizedValues);
        }

        return str_replace(' ', '-', $value);
    }

    public static function needsSanitization(string $value): bool
    {
        return str_contains($value, '&#38;')
            || str_contains($value, '&#60;')
            || str_contains($value, '&#62;')
            || str_contains($value, '&#34;')
            || str_contains($value, '&#39;');
    }

    public static function sanitizeHtmlEntities(string $value): string
    {
        $previous = '';
        $value    = preg_replace('/&#38;(#38;)*/', '&', $value) ?? $value;
        $value    = preg_replace('/&#60;(#60;)*/', '<', $value) ?? $value;
        $value    = preg_replace('/&#62;(#62;)*/', '>', $value) ?? $value;
        $value    = preg_replace('/&#34;(#34;)*/', '"', $value) ?? $value;
        $value    = preg_replace('/&#39;(#39;)*/', "'", $value) ?? $value;

        while ($value !== $previous) {
            $previous = $value;
            $value    = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }
}
