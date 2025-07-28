<?php

return [
    'name'        => 'Floodlight for B2B',
    'description' => 'Floodlight for B2B plugin: Detect and qualify companies from anonymous leads in Mautic, using the Floodlight for B2B service',
    'version'     => '1.2.3',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'others' => [
            'mautic.leuchtfeuerfloodlightb2b.services.whoispyservice' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\WhoisPyService::class,
                'arguments' => [
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.services.floodlight' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.connection.apiclient',
                    'monolog.logger.mautic',
                    '%mautic.leuchtfeuerfloodlightb2b.floodlight_api_url%',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.services.pdploader' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\PdpLoader::class,
                'arguments' => [
                    'mautic.helper.paths',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.connection.credentials' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\Credentials::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.connection.apiclient' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ApiClient::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.connection.credentials',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.reporter' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Reporter::class,
            ],

            'mautic.leuchtfeuerfloodlightb2b.factory.floodlightdto' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory::class,
                'arguments' => [
                    'mautic.lead.model.field',
                    'mautic.leuchtfeuerfloodlightb2b.helper.utility.domain',
                ],
            ],

            'mautic.leuchtfeuerfloodlightb2b.status200strategy' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status200Strategy::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains',
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains',
                    'mautic.lead.repository.company',
                    'mautic.lead.model.company',
                    'mautic.lead.model.lead',
                    'mautic.leuchtfeuerfloodlightb2b.helper.field',
                    'mautic.leuchtfeuerfloodlightb2b.helper.utility.domain',
                    'mautic.leuchtfeuerfloodlightb2b.factory.floodlightdto',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.status202strategy' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status202Strategy::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains',
                    'mautic.leuchtfeuerfloodlightb2b.factory.floodlightdto',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.status401strategy' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status401Strategy::class,
                'arguments' => [
                ],
            ],

            'mautic.leuchtfeuerfloodlightb2b.status404strategy' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status404Strategy::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains',
                    'mautic.leuchtfeuerfloodlightb2b.factory.floodlightdto',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.status429strategy' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status429Strategy::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.helper.utility.domain',
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains',
                    'mautic.lead.repository.company',
                    'mautic.lead.model.company',
                    'mautic.lead.model.lead',
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.utility.domain' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Utility\DomainUtility::class,
                'arguments' => [
                    'mautic.leuchtfeuerfloodlightb2b.services.whoispyservice',
                    'mautic.leuchtfeuerfloodlightb2b.helper.keywordblacklist',
                    'mautic.leuchtfeuerfloodlightb2b.helper.domainblacklist',
                    'mautic.helper.paths',
                    'mautic.leuchtfeuerfloodlightb2b.services.pdploader',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.floodlight_lead_name_helper' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FloodlightLeadNameHelper::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
                'tags' => [
                    'twig.extension',
                ],
            ],
        ],
        'helpers' => [
            'mautic.leuchtfeuerfloodlightb2b.helper.install' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\InstallHelper::class,
                'arguments' => [
                    'mautic.helper.paths',
                    'mautic.leuchtfeuerfloodlightb2b.helper.field',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.field' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper::class,
                'arguments' => [
                    'mautic.lead.model.field',
                    'form.factory',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.domainblacklist' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper::class,
                'arguments' => [
                    'mautic.helper.paths',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.keywordblacklist' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\KeywordBlacklistHelper::class,
                'arguments' => [
                    'mautic.helper.paths',
                ],
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.iplookuprules' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper::class,
                'arguments' => [
                    'mautic.page.model.page',
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains',
                    'mautic.leuchtfeuerfloodlightb2b.services.floodlight',
                    'mautic.leuchtfeuerfloodlightb2b.helper.reporter',
                    'mautic.leuchtfeuerfloodlightb2b.status200strategy',
                    'mautic.leuchtfeuerfloodlightb2b.status202strategy',
                    'mautic.leuchtfeuerfloodlightb2b.status404strategy',
                    'mautic.leuchtfeuerfloodlightb2b.status429strategy',
                    'mautic.integration.leuchtfeuerfloodlightb2b.config',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.leuchtfeuerfloodlightb2b' => [
                'class' => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.leuchtfeuerfloodlightb2b.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.leuchtfeuerfloodlightb2b.config' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
    ],
    'parameters' => [
        'minimum_hour_to_new_entry' => 1, // Minimum hour to new entry, IN HOUR
    ],
];
