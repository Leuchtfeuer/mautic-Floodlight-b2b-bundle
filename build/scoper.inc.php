<?php

declare(strict_types=1);

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
