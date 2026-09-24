<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
        'Vendor',
        'Dashboard/UsageData.php',
        'Dashboard/UsageException.php',
    ];
    $services->load('MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');
    $services->get(MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageProvider::class)
        ->arg('$floodlightApiUrl', '%mautic.leuchtfeuerfloodlightb2b.floodlight_api_url%');
    $services->load('MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);
    $services->alias('mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel::class);
    $services->alias('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel::class);
};
