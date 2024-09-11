<?php

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

        if (!empty($properties)) {
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
        foreach ($fieldsToUpdate as $key => $values) {
            $field = $this->fieldModel->getRepository()->findOneBy(['alias' => $key]);
            if (!$field instanceof LeadField) {
                continue;
            }
            $this->addProperties($field, $values);
        }
    }

    /**
     * @param array<array> $fieldsAllowedToBeUpdated
     *
     * @return array<array>
     */
    private function getFieldsWithPropertiesToUpdate(array $fieldsAllowedToBeUpdated, FloodlightDTO $dataFloodlight): array
    {
        $requestDataFloodlight = $dataFloodlight->getPureData()['content']['company'];

        $result = [];
        foreach ($fieldsAllowedToBeUpdated as $key => $fieldAllowedToBeUpdated) {
            $externalAlias = $this->getExternalAlias($fieldAllowedToBeUpdated['alias']);
            if (!array_key_exists($externalAlias, $requestDataFloodlight)) {
                continue;
            }
            $valuesField = $requestDataFloodlight[$externalAlias];
            if (is_string($valuesField)) {
                $valuesField = [$valuesField];
            }

            if (!isset($fieldAllowedToBeUpdated['properties']['list'])) {
                $result[$key] = $valuesField;
                continue;
            }
            $listValues = $this->getListFieldValues($fieldAllowedToBeUpdated['properties']['list']);
            foreach ($valuesField as $value) {
                $value = html_entity_decode($value);
                if (in_array($value, $listValues)) {
                    continue;
                }
                $result[$key][] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<array> $list
     *
     * @return array<string>
     */
    private function getListFieldValues(array $list): array
    {
        $values = [];
        foreach ($list as $item) {
            $values[] = html_entity_decode($item['value']);
        }

        return $values;
    }

    private function getLocalAlias(string $alias): string
    {
        if ('company_technologies' === $alias) {
            $alias = 'companytechnologies';
        }

        return 'f4b_'.$alias;
    }

    private function getExternalAlias(string $alias): string
    {
        if ('f4b_companytechnologies' === $alias) {
            $alias = 'f4b_company_technologies';
        }

        return str_replace('f4b_', '', $alias);
    }

    /**
     * @return array<string,array>
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
        if (!isset($newProperties['list'])) {
            $newProperties['list'] = [];
        }

        foreach ($properties as $value) {
            $label                   = $value;
            $value                   = str_replace([' '], ['-'], $value);
            $newProperties['list'][] = [
                'label' => $label,
                'value' => $value,
            ];
        }
        $result = $this->fieldModel->setFieldProperties($field, $newProperties);
        if ($result) {
            $this->fieldModel->saveEntity($field);
        } else {
            $this->logger->error('Field properties could not be saved');
        }
    }
}
