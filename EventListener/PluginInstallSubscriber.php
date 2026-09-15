<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\Helper\FilePathResolver;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\InstallHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginInstallSubscriber implements EventSubscriberInterface
{
    public const FIELD_DATA =
        [
            [
                'alias'           => 'f4b_companyfloodlight_id',
                'name'            => 'Floodlight Company ID',
                'type'            => 'text',
                'externalKeyName' => 'companyfloodlight_id',
            ],
            [
                'alias'             => 'f4b_companycrunchbase_handle',
                'name'              => 'Crunchbase Handle',
                'type'              => 'text',
                'externalKeyName'   => 'companycrunchbase_handle',
            ],
            [
                'alias'           => 'f4b_companyname_legal',
                'name'            => 'Legal Company Name',
                'type'            => 'text',
                'limit'           => 255,
                'externalKeyName' => 'companyname_legal',
            ],
            [
                'alias'           => 'f4b_companyname_alternatives',
                'name'            => 'Alternative Company Names',
                'type'            => 'text',
                'isIndex'         => true,
                'limit'           => 255,
                'externalKeyName' => 'companyname_alternatives',
            ],
            [
                'alias'           => 'f4b_companyindustry_linkedin',
                'name'            => 'LinkedIn Industry',
                'type'            => 'text',
                'limit'           => 255,
                'isIndex'         => true,
                'externalKeyName' => 'companyindustry_linkedin',
            ],
            [
                'alias'           => 'f4b_companyindustry_list',
                'name'            => 'Industry List',
                'type'            => 'multiselect',
                'limit'           => 255,
                'properties'      => [
                    'list' => [
                        ['label' => 'Energy', 'value' => 'energy'],
                    ],
                ],
                'externalKeyName' => 'companyindustry_list',
            ],
            [
                'alias'           => 'f4b_companylabels',
                'name'            => 'Company Labels',
                'type'            => 'multiselect',
                'limit'           => 255,
                'properties'      => [
                    'list' => [
                        ['label' => 'B2B', 'value' => 'b2b'],
                    ],
                ],
                'externalKeyName' => 'companylabels',
            ],
            [
                'alias' => 'f4b_companyindustry_naicscode',
                'name'  => 'NAICS Code',
                'type'  => 'number',
            ],
            [
                'alias'      => 'f4b_companyindustry_icb_industrygroup',
                'name'       => 'ICB Industry Group',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => 'Personal & Household Goods', 'value' => 'personal-household-goods'],
                    ],
                ],
                'externalKeyName' => 'companyindustry_icb_industrygroup',
            ],
            [
                'alias'      => 'f4b_companyindustry_icb_industry',
                'name'       => 'ICB Industry',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => 'Personal Goods', 'value' => 'personal-goods'],
                    ],
                ],
                'externalKeyName' => 'companyindustry_icb_industry',
            ],
            [
                'alias'      => 'f4b_companyindustry_icb_subIndustry',
                'name'       => 'ICB Sub Industry',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => 'Footwear', 'value' => 'footwear'],
                    ],
                ],
                'externalKeyName' => 'companyindustry_icb_subindustry',
            ],
            [
                'alias'      => 'f4b_companyindustry_icb_sector',
                'name'       => 'ICB Sector',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => 'Consumer Goods', 'value' => 'consumer-goods'],
                    ],
                ],
                'externalKeyName' => 'companyindustry_icb_sector',
            ],
            [
                'alias'           => 'f4b_companydescription_long',
                'name'            => 'Company Long Description',
                'type'            => 'textarea',
                'limit'           => 2560,
                'isIndex'         => true,
                'externalKeyName' => 'companydescription_long',
            ],
            [
                'alias'           => 'f4b_companydescription',
                'name'            => 'Company Description',
                'type'            => 'text',
                'limit'           => 255,
                'isIndex'         => true,
                'externalKeyName' => 'companydescription',
            ],
            [
                'alias'           => 'f4b_companyfounding_year',
                'name'            => 'Founding Year',
                'type'            => 'number',
                'externalKeyName' => 'companyfounding_year',
            ],
            [
                'alias'           => 'f4b_companystate_code',
                'name'            => 'State Code',
                'type'            => 'text',
                'limit'           => 4,
                'externalKeyName' => 'companystate_code',
            ],
            [
                'alias'           => 'f4b_companyrank_tranco',
                'name'            => 'Tranco Rank',
                'type'            => 'number',
                'externalKeyName' => 'companyrank_tranco',
            ],
            [
                'alias'      => 'f4b_companyemployees_range',
                'name'       => 'Employee Range',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => '10K+', 'value' => '10k+'],
                    ],
                ],
                'externalKeyName' => 'companyemployees_range',
            ],
            [
                'alias' => 'f4b_companymarketcap',
                'name'  => 'Market Cap',
                'type'  => 'number',
            ],
            [
                'alias'      => 'f4b_companyannual_revenue_range',
                'name'       => 'Annual Revenue Range',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => '$10B+', 'value' => '$10B+'],
                    ],
                ],
            ],
            [
                'alias'           => 'f4b_companyfunding_raised',
                'name'            => 'Funding Raised',
                'type'            => 'number',
                'externalKeyName' => 'companyfunding_raised',
            ],
            [
                'alias'           => 'f4b_companytechnologies',
                'name'            => 'Technologies',
                'limit'           => 255,
                'type'            => 'multiselect',
                'properties'      => [
                    'list' => [
                        ['label' => 'Mautic', 'value' => 'mautic'],
                    ],
                ],
                'externalKeyName' => 'company_technologies',
            ],
            [
                'alias'           => 'f4b_companyfacebook_handle',
                'name'            => 'Facebook Handle',
                'type'            => 'text',
                'externalKeyName' => 'companyfacebook_handle',
            ],
            [
                'alias'           => 'f4b_companylinkedin_handle',
                'name'            => 'LinkedIn Handle',
                'type'            => 'text',
                'externalKeyName' => 'companylinkedin_handle',
            ],
            [
                'alias'           => 'f4b_companytwitter_handle',
                'name'            => 'Twitter Handle',
                'type'            => 'text',
                'externalKeyName' => 'companytwitter_handle',
            ],
            [
                'alias'           => 'f4b_companytwitter_followers',
                'name'            => 'Twitter Followers',
                'type'            => 'number',
                'externalKeyName' => 'companytwitter_followers',
            ],
            [
                'alias'           => 'companylogo_filename',
                'name'            => 'Company Logo Filename',
                'type'            => 'text',
                'group'           => 'core',
                'externalKeyName' => 'companylogo_filename',
            ],
            [
                'alias'      => 'f4b_companytype',
                'name'       => 'Company Type',
                'type'       => 'select',
                'properties' => [
                    'list' => [
                        ['label' => 'Public', 'value' => 'public'],
                        ['label' => 'Private', 'value' => 'private'],
                        ['label' => 'Non-Profit', 'value' => 'non-profit'],
                        ['label' => 'Government', 'value' => 'government'],
                    ],
                ],
                'externalKeyName' => 'companytype',
            ],
            [
                'alias'           => 'f4b_companyticker',
                'name'            => 'Company Ticker',
                'type'            => 'text',
                'limit'           => 6,
                'externalKeyName' => 'companyticker',
            ],
            [
                'alias'           => 'f4b_companytimezone',
                'name'            => 'Company Timezone',
                'type'            => 'timezone',
                'externalKeyName' => 'companytimezone',
            ],
            [
                'alias' => 'f4b_companycountry_code',
                'name'  => 'Country Code',
                'type'  => 'text',
                'limit' => 2,
            ],
            [
                'alias' => 'f4b_iplookup_whois_json',
                'name'  => 'IP Lookup Whois',
                'type'  => 'textarea',
            ],
            [
                'alias' => 'f4b_enrichment_date',
                'name'  => 'Enrichment Date',
                'type'  => 'datetime',
            ],
        ];

    public function __construct(private InstallHelper $installHelper, private PathsHelper $pathsHelper, private FilePathResolver $filePathResolver)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL                    => ['onPluginInstall', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext('Floodlight for B2B')) {
            return;
        }

        $this->executeInstallLogic();
    }

    private function executeInstallLogic(): void
    {
        $this->installHelper->copyBlacklistFiles();
        $logoPath = $this->pathsHelper->getMediaPath().'/logos';
        $this->filePathResolver->createDirectory($logoPath);

        foreach (self::FIELD_DATA as $fieldData) {
            $options = [];
            if (isset($fieldData['properties'])) {
                $options['properties'] = $fieldData['properties'];
            }
            if (isset($fieldData['limit'])) {
                $options['limit'] = $fieldData['limit'];
            }

            $options['isIndex'] = false;
            if (isset($fieldData['isIndex'])) {
                $options['isIndex'] = $fieldData['isIndex'];
            }

            if (isset($fieldData['group'])) {
                $options['group'] = $fieldData['group'];
            }

            $this->installHelper->installFields(
                $fieldData['alias'],
                $fieldData['name'],
                $fieldData['type'],
                $options
            );
        }
    }
}
