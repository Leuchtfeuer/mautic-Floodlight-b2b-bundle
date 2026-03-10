<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Symfony\Component\HttpFoundation\JsonResponse;

interface FloodlightServiceInterface
{
    /**
     * @return mixed[]|false
     */
    public function get(string $ip): bool|array;

    /**
     * Validates API credentials against the Floodlight endpoint.
     */
    public function validateApiKey(): JsonResponse;
}
