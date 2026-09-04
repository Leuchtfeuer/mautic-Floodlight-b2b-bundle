<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

class FreeEmailDomainHelper
{
    private const LIST_FILE_PATH = __DIR__.'/../Config/f4b_free_email_domains.json';

    /** @var array<string>|null */
    private ?array $domains = null;

    public function isFreemailer(string $domain): bool
    {
        return in_array(strtolower($domain), $this->getDomains(), true);
    }

    /**
     * @return array<string>
     */
    private function getDomains(): array
    {
        if (null === $this->domains) {
            $this->domains = $this->readFile();
        }

        return $this->domains;
    }

    /**
     * @return array<string>
     */
    private function readFile(): array
    {
        if (!file_exists(self::LIST_FILE_PATH)) {
            return [];
        }

        $fileContent = file_get_contents(self::LIST_FILE_PATH);
        if (false === $fileContent) {
            return [];
        }

        $decoded = json_decode($fileContent, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('strtolower', $decoded);
    }
}
