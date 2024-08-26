<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;

class InstallHelper
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private MauticFactory $factory,
        private PathsHelper $pathsHelper
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

    public function installFields(string $alias, string $name, string $type): void
    {
        $tablePrefix   = $this->coreParametersHelper->get('table_prefix');
        $q             = $this->factory->getEntityManager()->getConnection();
        $sql           = 'Select * from '.$tablePrefix.'lead_fields where alias = "'.$alias.'"';
        $stmt          = $q->prepare($sql);
        $result        = $stmt->executeStatement();
        $datetime      = \date('Y-m-d H:i:s');
        if (empty($result)) {
            $sql = 'INSERT INTO '.$tablePrefix.'lead_fields 
            (
                is_published, 
                type, 
                alias, 
                label, 
                object, 
                field_group, 
                field_order, 
                properties, 
                date_added, 
                date_modified,
                is_required,
                is_fixed,
                is_visible,
                is_short_visible,
                is_listable,
                is_publicly_updatable
                ) VALUES (
                1, 
                "'.$type.'", 
                "'.$alias.'", 
                "'.$name.'", 
                "company", 
                "core", 
                1, 
                "a:0:{}", 
                "'.$datetime.'", 
                "'.$datetime.'",
                0,
                0,
                1,
                0,
                1,
                0
            )';
            $stmt = $q->prepare($sql);
            $stmt->executeQuery();
            if ('textarea' == $type) {
                $type = 'longtext';
            }
            $sql  = 'ALTER TABLE '.$tablePrefix.'companies ADD '.$alias.' '.$type;
            $stmt = $q->prepare($sql);
            $stmt->executeQuery();
        }
    }
}
