<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Symfony\Component\HttpFoundation\JsonResponse;

interface FloodlightServiceInterface
{
    public function get(string $ip): bool|array;
    
    /**
     * Validates API credentials against the Floodlight endpoint
     */
    public function validateApiKey(): JsonResponse;
}
