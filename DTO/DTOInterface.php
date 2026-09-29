<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

interface DTOInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getPureData(): array;
}
