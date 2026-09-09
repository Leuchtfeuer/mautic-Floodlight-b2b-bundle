<?php

declare(strict_types=1);

/*
 * Scoped to Vendor/Pdp only, not the whole plugin: scoping the whole tree
 * previously broke a real install because the exclude-namespaces blacklist
 * missed Twig (used by Helper/FloodlightLeadNameHelper.php).
 */

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Vendor',

    'finders' => [
        Finder::create()
            ->files()
            ->name('*.php')
            ->in(__DIR__.'/Vendor/Pdp'),
    ],
];
