<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Helper\PathsHelper;

class KeywordBlacklistHelper
{
    private string $blacklistPath;

    /**
     * @param array<string> $blacklist
     */
    public function __construct(
        private PathsHelper $pathsHelper,
        private array $blacklist = []
    ) {
        $this->blacklistPath = $this->pathsHelper->getRootPath().'/config/f4b_domain_keyword_blacklist.php';
        $this->blacklist     = $this->readFile();
    }

    /**
     * @return array<string>
     */
    private function readFile(): array
    {
        $fileContent = file_get_contents($this->blacklistPath);
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
}
