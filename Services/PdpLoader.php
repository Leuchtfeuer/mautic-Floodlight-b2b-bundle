<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Mautic\CoreBundle\Helper\PathsHelper;

/**
 * Service to load the embedded php-domain-parser library.
 */
class PdpLoader
{
    private static bool $loaded = false;

    public function __construct(
        private PathsHelper $pathsHelper
    ) {
    }

    public function load(): void
    {
        if (self::$loaded) {
            return;
        }

        if ($this->isAvailableViaComposer()) {
            self::$loaded = true;

            return;
        }

        $basePath = $this->pathsHelper->getPluginsPath().'/LeuchtfeuerFloodlightB2BBundle/Vendor/Pdp/src/';

        if (!file_exists($basePath.'Rules.php')) {
            throw new \RuntimeException('PHP Domain Parser library not found');
        }

        $this->loadFiles($basePath);
        self::$loaded = true;
    }

    private function loadFiles(string $basePath): void
    {
        // Load interfaces in right order
        $interfaceFiles = [
            'CannotProcessHost.php',
            'ResourceUri.php',
            'DomainNameProvider.php',
            'Host.php',
            'DomainNameResolver.php',
            'DomainName.php',               // extends Host
            'ResolvedDomainName.php',       // extends Host, DomainNameProvider
            'EffectiveTopLevelDomain.php',  // extends Host, DomainNameProvider
            'PublicSuffixList.php',         // extends DomainNameResolver
            'TopLevelDomainList.php',       // extends multiple interfaces
        ];

        foreach ($interfaceFiles as $interfaceFile) {
            $filePath = $basePath.$interfaceFile;
            if (file_exists($filePath) && !interface_exists('Pdp\\'.pathinfo($interfaceFile, PATHINFO_FILENAME))) {
                require_once $filePath;
            }
        }

        // Load storage interfaces in right order
        $storageInterfaceFiles = [
            'Storage/PublicSuffixListClient.php',
            'Storage/TopLevelDomainListClient.php',
            'Storage/PublicSuffixListCache.php',
            'Storage/TopLevelDomainListCache.php',
            'Storage/PublicSuffixListStorageFactory.php',
            'Storage/TopLevelDomainListStorageFactory.php',
            'Storage/PublicSuffixListStorage.php',      // extends PublicSuffixListClient
            'Storage/TopLevelDomainListStorage.php',    // extends TopLevelDomainListClient
        ];

        foreach ($storageInterfaceFiles as $interfaceFile) {
            $filePath = $basePath.$interfaceFile;
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }

        // Load exception/error classes in right order
        $exceptionFiles = [
            'SyntaxError.php',
            'UnableToLoadResource.php',
            'UnableToLoadPublicSuffixList.php',
            'UnableToLoadTopLevelDomainList.php',
            'UnableToResolveDomain.php',
        ];

        foreach ($exceptionFiles as $exceptionFile) {
            $filePath = $basePath.$exceptionFile;
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }

        // Load core classes in right order
        $coreFiles = [
            'Idna.php',
            'IdnaInfo.php',
            'Stream.php',
            'RegisteredName.php',
            'Suffix.php',         // implements EffectiveTopLevelDomain
            'Domain.php',         // implements DomainName, uses RegisteredName
            'ResolvedDomain.php', // implements ResolvedDomainName
            'Rules.php',          // implements PublicSuffixList
            'TopLevelDomains.php', // implements TopLevelDomainList
        ];

        foreach ($coreFiles as $file) {
            $filePath = $basePath.$file;
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }

        // Load storage classes in right order
        $storageClassFiles = [
            'Storage/TimeToLive.php',
            'Storage/PublicSuffixListPsr18Client.php',   // implements PublicSuffixListClient
            'Storage/TopLevelDomainListPsr18Client.php', // implements TopLevelDomainListClient
            'Storage/PublicSuffixListPsr16Cache.php',    // implements PublicSuffixListCache
            'Storage/TopLevelDomainListPsr16Cache.php',  // implements TopLevelDomainListCache
            'Storage/RulesStorage.php',                  // implements PublicSuffixListStorage
            'Storage/TopLevelDomainsStorage.php',        // implements TopLevelDomainListStorage
            'Storage/PsrStorageFactory.php',             // implements PublicSuffixListStorageFactory
        ];

        foreach ($storageClassFiles as $classFile) {
            $filePath = $basePath.$classFile;
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }
    }

    public function isLoaded(): bool
    {
        return self::$loaded;
    }

    public function isAvailableViaComposer(): bool
    {
        return class_exists('Pdp\\Rules') && class_exists('Pdp\\Domain');
    }
}
