<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Helper\PathsHelper;

class InstallHelper
{
    public function __construct(
        private PathsHelper $pathsHelper,
        private FieldHelper $fieldHelper
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

    public function installWhoisPythonLib(): bool
    {
        $outputList    = shell_exec('pip list');
        $outputInstall = [];
        $error         = false;

        if (is_string($outputList) && !str_contains($outputList, 'python-whois')) {
            $outputInstall[] = shell_exec('pip install python-whois --break-system-packages');
        }

        if (is_string($outputList) && !str_contains($outputList, 'ipwhois')) {
            $outputInstall[] = shell_exec('pip install ipwhois --break-system-packages');
        }

        if (empty($outputInstall)) {
            return true;
        }

        foreach ($outputInstall as $output) {
            if (is_string($output) && !str_contains($output, 'Successfully installed')) {
                $error = true;
                break;
            }
        }
        if (!empty($error)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function installFields(string $alias, string $name, string $type, array $options =[]): void
    {
        $this->fieldHelper->createField($alias, $name, $type, $options);
    }
}
