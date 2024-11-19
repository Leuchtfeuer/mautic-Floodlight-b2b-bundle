<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;

class InstallHelper
{
    public function __construct(
        private PathsHelper $pathsHelper,
        private FieldHelper $fieldHelper
    ) {
    }

    public function copyBlacklistFile(): void
    {
        $blacklistFile = $this->pathsHelper->getRootPath().'/config/blacklist.php';
        if (!file_exists($blacklistFile)) {
            $configPath   = $this->pathsHelper->getPluginsPath().'/LeuchtfeuerFloodlightB2BBundle/Config/blacklist.php';
            $configPathTo = $this->pathsHelper->getRootPath().'/config/blacklist.php';
            copy($configPath, $configPathTo);
        }
    }

    public function installWhoisPythonLib(): bool
    {
        $outputList    = shell_exec('pip list');
        $outputInstall = [];
        $error         = false;

        if (!str_contains($outputList, 'python-whois')) {
            $outputInstall[] = shell_exec('pip install python-whois --break-system-packages');
        }

        if (!str_contains($outputList, 'ipwhois')) {
            $outputInstall[] = shell_exec('pip install ipwhois --break-system-packages');
        }

        if (empty($outputInstall)) {
            return true;
        }

        foreach ($outputInstall as $output) {
            if (!str_contains($output, 'Successfully installed')) {
                $error = true;
                break;
            }
        }
        if (!empty($error)) {
            return false;
        }

        return true;
    }

    public function installFields(string $alias, string $name, string $type, array $options =[]): void
    {
        $this->fieldHelper->createField($alias, $name, $type, $options);
    }
}
