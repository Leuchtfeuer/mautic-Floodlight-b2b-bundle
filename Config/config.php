<?php

return [
    'name'        => 'Floodlight for B2B',
    'description' => 'Floodlight for B2B plugin: Detect and qualify companies from anonymous leads in Mautic, using the Floodlight for B2B service',
    'version'     => '5.8.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
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
