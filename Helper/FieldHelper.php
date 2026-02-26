<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;

class FieldHelper
{
    public const DEFAULT_VALUES = [
        'object'                 => 'company',
        'group'                  => 'professional',
        'is_required'            => false,
        'is_fixed'               => false,
        'is_visible'             => true,
        'is_short_visible'       => true,
        'is_listable'            => true,
        'is_publicity_updatable' => false,
        'is_unique_identifier'   => false,
        'is_index'               => false,
    ];

    public const KEY_FIELDS_TO_UPDATE = [
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

    public function __construct(private FieldModel $fieldModel, protected FormFactoryInterface $formFactory, protected LoggerInterface $logger)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createField(string $alias, string $label, string $type, array $options = []): void
    {
        // Update existing field
        $existingField = $this->fieldModel->getRepository()->findOneBy([
            'alias'  => $alias,
            'object' => self::DEFAULT_VALUES['object'],
        ]);

        if ($existingField) {
            if (!empty($options['limit']) && is_int($options['limit'])) {
                $currentLimit = $existingField->getCharLengthLimit();
                if ($currentLimit !== $options['limit']) {
                    $existingField->setCharLengthLimit($options['limit']);

                    try {
                        $this->fieldModel->saveEntity($existingField);
                    } catch (\Exception $e) {
                        $this->logger->error('Field character length limit could not be updated: '.$e->getMessage());
                    }
                }
            }

            return;
        }

        // Create new field
        $properties = [];
        if (!empty($options['properties'])) {
            $properties = $options['properties'];
        }

        $field = new LeadField();
        $field->setAlias($alias);
        $field->setLabel($label);
        $field->setType($type);
        $field->setObject(self::DEFAULT_VALUES['object']);
        $field->setGroup(self::DEFAULT_VALUES['group']);
        $field->setIsRequired(self::DEFAULT_VALUES['is_required']);
        $field->setIsFixed(self::DEFAULT_VALUES['is_fixed']);
        $field->setIsVisible(self::DEFAULT_VALUES['is_visible']);
        $field->setIsShortVisible(self::DEFAULT_VALUES['is_short_visible']);
        $field->setIsListable(self::DEFAULT_VALUES['is_listable']);
        $field->setIsPubliclyUpdatable(self::DEFAULT_VALUES['is_publicity_updatable']);
        $field->setIsUniqueIdentifier(self::DEFAULT_VALUES['is_unique_identifier']);
        $field->setIsIndex(self::DEFAULT_VALUES['is_index']);

        if (!empty($options['limit']) && is_int($options['limit'])) {
            $field->setCharLengthLimit($options['limit']);
        }

        if (!empty($properties) && is_array($properties)) {
            $result = $this->fieldModel->setFieldProperties($field, $properties);
        }

        try {
            $this->fieldModel->saveEntity($field);
        } catch (\Exception $e) {
            $this->logger->error('Field could not be saved : '.$e->getMessage());
        }
    }

    public function updateProperties(FloodlightDTO $floodlightDTO): void
    {
        $fieldsAllowedToBeUpdated = $this->getFieldsPropertiesAllowToBeUpdated();
        $fieldsToUpdate           = $this->getFieldsWithPropertiesToUpdate($fieldsAllowedToBeUpdated, $floodlightDTO);
        if (false === $fieldsToUpdate) {
            return;
        }
        foreach ($fieldsToUpdate as $key => $values) {
            $field = $this->fieldModel->getRepository()->findOneBy(['alias' => $key]);
            if (!$field instanceof LeadField) {
                continue;
            }
            $this->addProperties($field, $values);
        }
    }

    /**
     * Update character length limit for an existing field.
     */
    public function updateFieldCharLengthLimit(string $alias, int $newLimit): bool
    {
        $field = $this->fieldModel->getRepository()->findOneBy(['alias' => $alias]);

        if (!$field instanceof LeadField) {
            return false;
        }

        $field->setCharLengthLimit($newLimit);

        try {
            $this->fieldModel->saveEntity($field);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param array<string|mixed> $fieldsAllowedToBeUpdated
     *
     * @return array<string,array<string>>|false
     */
    private function getFieldsWithPropertiesToUpdate(array $fieldsAllowedToBeUpdated, FloodlightDTO $dataFloodlight): array|false
    {
        $requestDataFloodlight = $dataFloodlight->getPureData();
        $result                = [];

        foreach ($fieldsAllowedToBeUpdated as $key => $fieldAllowedToBeUpdated) {
            if (!is_array($fieldAllowedToBeUpdated) || !array_key_exists('alias', $fieldAllowedToBeUpdated)) {
                continue;
            }
            $externalAlias = $this->getExternalAlias($fieldAllowedToBeUpdated['alias']);
            if (!array_key_exists($externalAlias, $requestDataFloodlight)) {
                continue;
            }
            $valuesField = $requestDataFloodlight[$externalAlias];
            if (is_string($valuesField)) {
                $valuesField = [$valuesField];
            }

            if (!isset($fieldAllowedToBeUpdated['properties']['list'])) {
                $result[$key] = is_array($valuesField) ? $valuesField : [];
                continue;
            }

            if (!is_array($valuesField)) {
                continue;
            }

            $listValues = $this->getListFieldValues($fieldAllowedToBeUpdated['properties']['list']);

            foreach ($valuesField as $value) {
                $value              = $this->sanitizeHtmlEntities($value);
                $valueForComparison = str_replace([' '], ['-'], $value);
                if (in_array($valueForComparison, $listValues)) {
                    continue;
                }
                if (!isset($result[$key])) {
                    $result[$key] = [];
                }
                $result[$key][] = $value;
            }
        }

        if (empty($result)) {
            return false;
        }

        foreach ($result as $values) {
            if (!is_array($values)) {
                return false;
            }
        }

        return $result;
    }

    /**
     * @param array<array<string>> $list
     *
     * @return array<string>
     */
    private function getListFieldValues(array $list): array
    {
        $values = [];
        foreach ($list as $item) {
            $values[] = $this->sanitizeHtmlEntities($item['value']);
        }

        return $values;
    }

    private function getExternalAlias(string $alias): string
    {
        if ('f4b_companytechnologies' === $alias) {
            $alias = 'f4b_company_technologies';
        }

        return str_replace('f4b_', '', $alias);
    }

    /**
     * @return array<string,array<string>>
     */
    private function getFieldsPropertiesAllowToBeUpdated(): array
    {
        $fields         = $this->fieldModel->getFieldListWithProperties('company');
        $fieldsToUpdate = [];
        foreach (self::KEY_FIELDS_TO_UPDATE as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $fieldsToUpdate[$key] = $fields[$key];
        }

        return $fieldsToUpdate;
    }

    /**
     * @param array<string> $properties
     */
    public function addProperties(LeadField $field, array $properties): void
    {
        $newProperties = $field->getProperties();
        assert(is_array($newProperties));
        if (!isset($newProperties['list'])) {
            $newProperties['list'] = [];
        }

        foreach ($properties as $value) {
            $label                   = $this->sanitizeHtmlEntities($value);
            $value                   = str_replace([' '], ['-'], $label);
            $newProperties['list'][] = [
                'label' => $label,
                'value' => $value,
            ];
        }
        $result = $this->fieldModel->setFieldProperties($field, $newProperties);
        $field->setIsIndex(false);
        if ($result) {
            $this->fieldModel->saveEntity($field);
        } else {
            $this->logger->error('Field properties could not be saved');
        }
    }

    private function sanitizeHtmlEntities(string $value): string
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
