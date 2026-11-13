<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

interface FloodlightServiceInterface
{
    /**
     * @return mixed[]|false
     */
    public function get(string $ip): bool|array;
}
