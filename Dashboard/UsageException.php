<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard;

/**
 * Controlled failure categories exposed by the usage provider so the
 * dashboard presentation can distinguish widget states without inspecting
 * raw PSR-7 responses.
 */
final class UsageException extends \RuntimeException
{
    public const NOT_CONFIGURED      = 'not_configured';

    public const INVALID_CREDENTIALS = 'invalid_credentials';

    public const RATE_LIMITED        = 'rate_limited';

    public const UNAVAILABLE         = 'unavailable';

    /**
     * @param self::* $category
     */
    private function __construct(
        private string $category,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(self::NOT_CONFIGURED, 'Floodlight integration is not configured.');
    }

    public static function invalidCredentials(): self
    {
        return new self(self::INVALID_CREDENTIALS, 'Floodlight credentials are invalid.');
    }

    public static function rateLimited(): self
    {
        return new self(self::RATE_LIMITED, 'Floodlight usage endpoint is rate limited.');
    }

    public static function unavailable(string $message): self
    {
        return new self(self::UNAVAILABLE, $message);
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
