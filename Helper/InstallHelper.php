<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Helper\PathsHelper;

class InstallHelper
{
    public function __construct(
        private readonly PathsHelper $pathsHelper,
        private readonly FieldHelper $fieldHelper,
    ) {
    }

    public function copyBlacklistFiles(): void
    {
        $blacklistFilePath = $this->pathsHelper->getVendorRootPath().'/config/f4b_domain_blacklist.php';
        if (!file_exists($blacklistFilePath)) {
            $configPath   = $this->pathsHelper->getPluginsPath().'/LeuchtfeuerFloodlightB2BBundle/Config/f4b_domain_blacklist.php';
            copy($configPath, $blacklistFilePath);
        }

        $keywordBlacklistFilePath = $this->pathsHelper->getVendorRootPath().'/config/f4b_domain_keyword_blacklist.php';
        if (!file_exists($keywordBlacklistFilePath)) {
            $configPath   = $this->pathsHelper->getPluginsPath().'/LeuchtfeuerFloodlightB2BBundle/Config/f4b_domain_keyword_blacklist.php';
            copy($configPath, $keywordBlacklistFilePath);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function installFields(string $alias, string $name, string $type, array $options =[]): void
    {
        $this->fieldHelper->createField($alias, $name, $type, $options);
    }
}
