<?php

return [
    'name'        => 'Floodlight for B2B',
    'description' => 'Floodlight for B2B plugin: Detect and qualify companies from anonymous leads in Mautic, using the Floodlight for B2B service',
    'version'     => '1.0.0',
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
                'class' => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper::class,
            ],
            'mautic.leuchtfeuerfloodlightb2b.helper.iplookuprules' => [
                'class'     => MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper::class,
                'arguments' => [
                    'mautic.page.model.page',
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains',
                    'mautic.leuchtfeuerfloodlightb2b.services.whoispyservice',
                    'mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains',
                    'mautic.leuchtfeuerfloodlightb2b.services.floodlight',
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
