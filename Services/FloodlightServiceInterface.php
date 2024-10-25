<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

interface FloodlightServiceInterface
{
    public function get(string $ip): bool|array;
}
