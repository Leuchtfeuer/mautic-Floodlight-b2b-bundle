<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Helper\PathsHelper;

class DomainBlacklistHelper
{
    private string $blacklistPath;
    private bool $blacklistLoaded = false;

    /** @var array<string> */
    private array $blacklist = [];

    public function __construct(
        private PathsHelper $pathsHelper,
    ) {
    }

    public function setBlacklist(): void
    {
        $this->blacklistPath = $this->pathsHelper->getVendorRootPath().'/config/f4b_domain_blacklist.php';
        $this->blacklist     = $this->readFile();
    }

    /**
     * @return array<string>
     */
    private function readFile(): array
    {
        if (!file_exists($this->blacklistPath)) {
            return [];
        }

        $fileContent           = file_get_contents($this->blacklistPath);
        $this->blacklistLoaded = true;
        if (false === $fileContent) {
            return [];
        }

        return explode(PHP_EOL, $fileContent);
    }

    public function add(string $blacklistDomain): void
    {
        if (in_array($blacklistDomain, $this->blacklist, true)) {
            return;
        }
        $this->blacklist[] = $blacklistDomain;
        $this->saveFile();
    }

    private function saveFile(): void
    {
        $textFile = implode(PHP_EOL, $this->blacklist);
        file_put_contents($this->blacklistPath, $textFile);
    }

    /**
     * @return array<string>
     */
    public function get(): array
    {
        return $this->blacklist;
    }

    public function remove(string $blacklistDomain): void
    {
        $key = array_search($blacklistDomain, $this->blacklist, true);
        if (false === $key) {
            return;
        }
        unset($this->blacklist[$key]);
        $this->saveFile();
    }

    public function checkIfBlacklistLoaded(): bool
    {
        return $this->blacklistLoaded;
    }
}
