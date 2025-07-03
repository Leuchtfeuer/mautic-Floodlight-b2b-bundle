<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class LeuchtfeuerFloodlightB2BExtension extends Extension
{
    private const DEFAULT_FLOODLIGHT_API_URL = 'https://api-dev.floodlight-b2b.com';
    private const ENV_FLOODLIGHT_API_URL     = 'FLOODLIGHT_API_URL'; // Environment variable name
    private const PARAM_FLOODLIGHT_API_URL   = 'mautic.leuchtfeuerfloodlightb2b.floodlight_api_url';
    // Suffix for the key Mautic uses to store bundle parameters
    private const MAUTIC_BUNDLE_PARAMS_SUFFIX = '.parameters';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Config'));
        $loader->load('services.php');

        $apiUrl = self::DEFAULT_FLOODLIGHT_API_URL;

        // 1. Check environment variable
        $envUrl = getenv(self::ENV_FLOODLIGHT_API_URL);
        if (false !== $envUrl && '' !== trim($envUrl)) { // Ensure it's not just an empty string
            $apiUrl = trim($envUrl);
        } else {
            // 2. If no env var, check parameters defined in config.php (Mautic convention)
            // Mautic stores bundle parameters under 'mautic.' + extension_alias + '.parameters'
            // The extension_alias for LeuchtfeuerFloodlightB2BExtension is 'leuchtfeuer_floodlight_b2_b' (or similar based on exact Bundle name)
            // The parent::getAlias() method will return the correct, simple alias.
            $mauticConventionBundleParamsKey = 'mautic.'.$this->getAlias().self::MAUTIC_BUNDLE_PARAMS_SUFFIX;

            if ($container->hasParameter($mauticConventionBundleParamsKey)) {
                $bundleParameters = $container->getParameter($mauticConventionBundleParamsKey);
                if (is_array($bundleParameters) && isset($bundleParameters['floodlight_api_url'])) {
                    $apiUrl = $bundleParameters['floodlight_api_url'];
                }
            }
        }

        // Set the resolved API URL as a container parameter
        // This PARAM_FLOODLIGHT_API_URL ('mautic.leuchtfeuerfloodlightb2b.floodlight_api_url')
        // must exactly match the string used in config.php for the service argument.
        $container->setParameter(self::PARAM_FLOODLIGHT_API_URL, $apiUrl);
    }

    // By not overriding getAlias(), we use the default behavior from Symfony\Component\DependencyInjection\Extension\Extension
    // which correctly generates an alias like 'leuchtfeuer_floodlight_b2_b'
    // from 'LeuchtfeuerFloodlightB2BBundle' (after it's processed by the Bundle class).
    // The Bundle class (LeuchtfeuerFloodlightB2BBundle.php) usually doesn't override getContainerExtension()
    // so the default alias calculation is used.
}
