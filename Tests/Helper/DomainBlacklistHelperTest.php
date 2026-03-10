<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

class DomainBlacklistHelperTest extends MauticMysqlTestCase
{
    private DomainBlacklistHelper $domainBlackList;
    private string $domain;

    public function setUp(): void
    {
        parent::setUp();

        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom  = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }

        $pathsHelper = self::getContainer()->get('mautic.helper.paths');
        \assert($pathsHelper instanceof \Mautic\CoreBundle\Helper\PathsHelper);
        $this->domainBlackList = new DomainBlacklistHelper($pathsHelper);
        $this->domainBlackList->setBlacklist();

        $this->domain = 'foobaa';
        $this->domainBlackList->remove('foobaa');
    }

    public function testGetDomain(): void
    {
        $this->assertIsArray($this->domainBlackList->get());
        $this->assertGreaterThan(10, count($this->domainBlackList->get()));
    }

    public function testAddDomain(): void
    {
        $this->assertFalse(in_array($this->domain, $this->domainBlackList->get()));
        $this->domainBlackList->add($this->domain);
        $this->assertTrue(in_array($this->domain, $this->domainBlackList->get()));
        $this->domainBlackList->remove($this->domain);
    }

    public function testRemoveDomain(): void
    {
        $this->domainBlackList->add($this->domain);
        $this->assertTrue(in_array($this->domain, $this->domainBlackList->get()));
        $this->domainBlackList->remove($this->domain);
        $this->assertFalse(in_array($this->domain, $this->domainBlackList->get()));
    }
}
