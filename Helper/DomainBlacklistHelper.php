<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

class DomainBlacklistHelper
{
    public const BLACKLIST_PATH_FILE = __DIR__.'/../../../config/blacklist.php';

    /**
     * @param array<string> $blacklist
     */
    public function __construct(private array $blacklist = [])
    {
        $this->blacklist = $this->readFile();
    }

    /**
     * @return array<string>|null
     */
    private function readFile(): ?array
    {
        return explode(PHP_EOL, file_get_contents(self::BLACKLIST_PATH_FILE));
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
        file_put_contents(self::BLACKLIST_PATH_FILE, $textFile);
    }

    /**
     * @return array<string>|null
     */
    public function get(): ?array
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
