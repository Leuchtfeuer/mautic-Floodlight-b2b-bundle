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
    ];
    $services->load('MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');
    $services->load('MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);
    $services->alias('mautic.leuchtfeuerfloodlightb2b.model.floodlightleaddomains', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel::class);
    $services->alias('mautic.leuchtfeuerfloodlightb2b.model.floodlightcompanydomains', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel::class);
    $services->set(MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory::class)->autowire()->autoconfigure()->public();
    $services->alias('mautic.leuchtfeuerfloodlightb2b.factory.floodlightdto', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory::class);
    $services->alias('mautic.leuchtfeuerfloodlightb2b.status200strategy', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status200Strategy::class);
    $services->alias('mautic.leuchtfeuerfloodlightb2b.status429strategy', MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status429Strategy::class);
};
