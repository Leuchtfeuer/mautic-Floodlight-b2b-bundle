<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard;

/**
 * Immutable value object representing a validated Floodlight usage response.
 *
 * The plan is deliberately absent. The dashboard presentation supplies the
 * static value "Free", allowing a future plan endpoint to evolve independently
 * from usage retrieval.
 */
final class UsageData
{
    public function __construct(
        private int $remaining,
        private int $monthlyLimit,
        private int $buffer,
        private \DateTimeImmutable $nextRenewal,
    ) {
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function getMonthlyLimit(): int
    {
        return $this->monthlyLimit;
    }

    public function getBuffer(): int
    {
        return $this->buffer;
    }

    public function getNextRenewal(): \DateTimeImmutable
    {
        return $this->nextRenewal;
    }
}
