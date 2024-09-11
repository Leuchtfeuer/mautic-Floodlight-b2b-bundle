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

//    MULTISELECT EXAMPLE
//
// leadfield[label]: Test Multiselect
// leadfield[alias]:
// leadfield[object]: lead
// leadfield[group]: core
// leadfield[type]: multiselect
// leadfield[defaultValue][]:
// leadfield[properties][list][0][label]: a
// leadfield[properties][list][0][value]: a
// leadfield[properties][list][1][label]: b
// leadfield[properties][list][1][value]: b
// leadfield[properties][list][2][label]: c
// leadfield[properties][list][2][value]: c
// leadfield[order]: 1
// leadfield[isPublished]: 1
// leadfield[isRequired]: 0
// leadfield[isVisible]: 1
// leadfield[isShortVisible]: 1
// leadfield[isListable]: 1
// leadfield[isPubliclyUpdatable]: 0
// leadfield[isUniqueIdentifer]: 0
// leadfield[_token]: 70297b06b41b23f77067c68c42.eRRs3hVe3vCKIqHz2-marCUSY6JCjr3D-SS-jZsPShw.H3UUqnoEmZHkTZalmtjJw3YjNMB75_HyplbE4a59c088WD-dVCqRm9Nlyg
// leadfield[buttons][save]:

// SELECT EXAMPLE
// leadfield[label]: Tese Select
// leadfield[alias]:
// leadfield[object]: lead
// leadfield[group]: core
// leadfield[type]: select
// leadfield[defaultValue]:
// leadfield[properties][list][0][label]: a
// leadfield[properties][list][0][value]: a
// leadfield[properties][list][1][label]: b
// leadfield[properties][list][1][value]: b
// leadfield[properties][list][2][label]: c
// leadfield[properties][list][2][value]: c
// leadfield[order]: 47
// leadfield[isPublished]: 1
// leadfield[isRequired]: 0
// leadfield[isVisible]: 1
// leadfield[isShortVisible]: 1
// leadfield[isListable]: 1
// leadfield[isPubliclyUpdatable]: 0
// leadfield[isUniqueIdentifer]: 0
// leadfield[_token]: c457.I7ORbjtccslKkvzZFdUyULKwgyf2gUVqMoNmFkRlBMA.RdLpGlQGNagk_cuPVORhP-GB1EXP6AlbbfEcenEXPZNm_8Iteig9ohPVlw
// leadfield[buttons][save]:
    public function installFields(string $alias, string $name, string $type, array $options =[]): void
    {
        $this->fieldHelper->createField($alias, $name, $type, $options);
//        $tablePrefix   = $this->coreParametersHelper->get('table_prefix');
//        if ('test' == $_SERVER['APP_ENV']) {
//            $tablePrefix = $tablePrefix.$_SERVER['APP_ENV'].'_';
//        }
//        $q             = $this->factory->getEntityManager()->getConnection();
//        $sql           = 'Select * from '.$tablePrefix.'lead_fields where alias = "'.$alias.'"';
//        $stmt          = $q->prepare($sql);
//        $result        = $stmt->executeStatement();
//        $datetime      = \date('Y-m-d H:i:s');
//        if (empty($result)) {
//            $sql = 'INSERT INTO '.$tablePrefix.'lead_fields
//            (
//                is_published,
//                type,
//                alias,
//                label,
//                object,
//                field_group,
//                field_order,
//                properties,
//                date_added,
//                date_modified,
//                is_required,
//                is_fixed,
//                is_visible,
//                is_short_visible,
//                is_listable,
//                is_publicly_updatable
//                ) VALUES (
//                1,
//                "'.$type.'",
//                "'.$alias.'",
//                "'.$name.'",
//                "company",
//                "core",
//                1,
//                "a:0:{}",
//                "'.$datetime.'",
//                "'.$datetime.'",
//                0,
//                0,
//                1,
//                0,
//                1,
//                0
//            )';
//            $stmt = $q->prepare($sql);
//            $stmt->executeQuery();
//            if ('textarea' == $type) {
//                $type = 'longtext';
//            }
//            $sql  = 'ALTER TABLE '.$tablePrefix.'companies ADD '.$alias.' '.$type;
//            $stmt = $q->prepare($sql);
//            $stmt->executeQuery();
//        }
    }
}
