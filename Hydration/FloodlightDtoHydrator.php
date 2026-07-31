<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Hydration;

use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FieldDefinition;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FieldType;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightFieldRegistry;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CompanyFieldValueSanitizer;

class FloodlightDtoHydrator
{
    /** @var array<string, bool>|null */
    private ?array $listFieldCache = null;

    public function __construct(
        private FieldModel $fieldModel,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data, FloodlightDTO $dto): void
    {
        $this->hydrateSpecialFields($data, $dto);
        $this->hydrateRegistryFields($data, $dto);
        $this->mergeDomainAliases($dto);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateSpecialFields(array $data, FloodlightDTO $dto): void
    {
        $dto->setDomains($this->extractArray($data, 'primarydomain'));
        $dto->setStatusHttpRequest($this->extractInt($data, 'statusRequestCode'));
        $dto->setCompanyPrimaryDomain($this->extractString($data, 'primarydomain'));
        $dto->setDomainAliases($this->extractArray($data, 'domainaliases'));
        $dto->setIp($this->extractString($data, 'ip'));
        $dto->setIpHash($this->extractString($data, 'ip_hash'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateRegistryFields(array $data, FloodlightDTO $dto): void
    {
        foreach (FloodlightFieldRegistry::getDefinitions() as $def) {
            $value  = $this->extractValue($data, $def);
            $setter = 'set'.ucfirst($def->dtoProperty);
            $dto->$setter($value);
        }
    }

    private function mergeDomainAliases(FloodlightDTO $dto): void
    {
        if (empty($dto->getDomainAliases())) {
            return;
        }

        $domains = $dto->getDomains();
        foreach ($dto->getDomainAliases() as $domain) {
            $domains[] = $domain;
        }
        $dto->setDomains($domains);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractValue(array $data, FieldDefinition $def): mixed
    {
        return match ($def->type) {
            FieldType::STRING     => $this->extractStringForField($data, $def),
            FieldType::INT        => $this->extractIntForField($data, $def),
            FieldType::ARRAY      => $this->extractArrayForField($data, $def),
            FieldType::LIST_VALUE => $this->extractListValue($data, $def),
            FieldType::LIST_ARRAY => $this->extractListArray($data, $def),
        };
    }

    /**
     * Matches original getStringToLoad order: truncate THEN sanitize for strings.
     *
     * @param array<string, mixed> $data
     */
    private function extractStringForField(array $data, FieldDefinition $def): string
    {
        if (!isset($data[$def->apiKey])) {
            return '';
        }

        $raw = $data[$def->apiKey];

        if (is_string($raw)) {
            if ($def->truncate) {
                $raw = $this->truncateString($raw, $def->dbAlias);
            }

            return $this->sanitizeHtmlEntities($raw);
        }

        if (is_numeric($raw)) {
            $raw = (string) $raw;
            if ($def->truncate) {
                $raw = $this->truncateString($raw, $def->dbAlias);
            }

            return $raw;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractIntForField(array $data, FieldDefinition $def): ?int
    {
        $value = $this->extractInt($data, $def->apiKey);

        if (null === $value) {
            return null;
        }

        if ($def->truncate) {
            $value = $this->truncateInt($value, $def->dbAlias);
        }

        return $value;
    }

    /**
     * Matches original getArrayToLoad: string/numeric → sanitize+truncateString; array → truncateArray.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string>
     */
    private function extractArrayForField(array $data, FieldDefinition $def): array
    {
        if (!isset($data[$def->apiKey])) {
            return [];
        }

        $raw = $data[$def->apiKey];

        if (is_string($raw) || is_numeric($raw)) {
            $value = $this->sanitizeHtmlEntities((string) $raw);
            if ($def->truncate) {
                $value = $this->truncateString($value, $def->dbAlias);
            }

            return [$value];
        }

        if (is_array($raw)) {
            if ($def->truncate) {
                return $this->truncateArray($raw, $def->dbAlias);
            }

            return $raw;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractListValue(array $data, FieldDefinition $def): string
    {
        $value = $this->extractStringForField($data, $def);

        if ('' === $value) {
            return '';
        }

        if ($this->isListField($def->dbAlias)) {
            $value = str_replace([' '], ['-'], $value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string>
     */
    private function extractListArray(array $data, FieldDefinition $def): array
    {
        $value = $this->extractArrayForField($data, $def);

        if (empty($value)) {
            return [];
        }

        if ($this->isListField($def->dbAlias)) {
            $value = array_map(fn ($item) => str_replace([' '], ['-'], $item), $value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $key): string
    {
        if (!isset($data[$key])) {
            return '';
        }

        if (is_string($data[$key])) {
            return $this->sanitizeHtmlEntities($data[$key]);
        }

        if (is_numeric($data[$key])) {
            return (string) $data[$key];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractInt(array $data, string $key): ?int
    {
        if (!isset($data[$key])) {
            return null;
        }

        if (is_numeric($data[$key])) {
            return (int) $data[$key];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string>
     */
    private function extractArray(array $data, string $key): array
    {
        if (!isset($data[$key])) {
            return [];
        }

        if (is_string($data[$key]) || is_numeric($data[$key])) {
            return [$this->sanitizeHtmlEntities((string) $data[$key])];
        }

        if (is_array($data[$key])) {
            return $data[$key];
        }

        return [];
    }

    private function truncateString(string $value, string $dbAlias): string
    {
        $limit = $this->getFieldLimit($dbAlias);

        if (null === $limit || strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit);
    }

    /**
     * @param array<string> $values
     *
     * @return array<string>
     */
    private function truncateArray(array $values, string $dbAlias): array
    {
        if (empty($values)) {
            return $values;
        }

        $limit = $this->getFieldLimit($dbAlias);

        if (null === $limit) {
            return $values;
        }

        $result        = [];
        $pipeSeparated = '';

        foreach ($values as $value) {
            $value      = $this->sanitizeHtmlEntities($value);
            $testString = $pipeSeparated.($pipeSeparated ? '|' : '').$value;

            if (strlen($testString) <= $limit) {
                $pipeSeparated = $testString;
                $result[]      = $value;
            } else {
                break;
            }
        }

        return $result;
    }

    private function truncateInt(int $value, string $dbAlias): int
    {
        $limit = $this->getFieldLimit($dbAlias);

        if (null === $limit || strlen((string) $value) <= $limit) {
            return $value;
        }

        return (int) substr((string) $value, 0, $limit);
    }

    private function getFieldLimit(string $dbAlias): ?int
    {
        $field = $this->fieldModel->getEntityByAlias($dbAlias);

        return $field && method_exists($field, 'getCharLengthLimit') ? $field->getCharLengthLimit() : null;
    }

    private function isListField(string $fieldAlias): bool
    {
        if (null === $this->listFieldCache) {
            $this->listFieldCache = [];
            foreach ($this->fieldModel->getRepository()->findBy(['object' => 'company']) as $field) {
                $properties                               = $field->getProperties();
                $this->listFieldCache[$field->getAlias()] = is_array($properties)
                    && isset($properties['list'])
                    && is_array($properties['list']);
            }
        }

        return $this->listFieldCache[$fieldAlias] ?? false;
    }

    private function sanitizeHtmlEntities(string $value): string
    {
        return CompanyFieldValueSanitizer::sanitizeHtmlEntities($value);
    }
}
