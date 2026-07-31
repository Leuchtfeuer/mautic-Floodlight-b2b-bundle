<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Hydration\FloodlightDtoHydrator;

class FloodlightDtoFactory
{
    public function __construct(
        private FloodlightDtoHydrator $hydrator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): FloodlightDTO
    {
        $dto = new FloodlightDTO($data);
        $this->hydrator->hydrate($data, $dto);

        return $dto;
    }
}
