<?php

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

        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/blacklist.php';
        $pluginPathFrom  = self::getContainer()->get('mautic.helper.paths')->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }
        $this->domainBlackList = new DomainBlacklistHelper();
        $this->domain          = 'foobaa';
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
    }

    public function testRemoveDomain(): void
    {
        $this->domainBlackList->add($this->domain);
        $this->assertTrue(in_array($this->domain, $this->domainBlackList->get()));
        $this->domainBlackList->remove($this->domain);
        $this->assertFalse(in_array($this->domain, $this->domainBlackList->get()));
    }

    public function beforeTearDown(): void
    {
        $configPathTo    = self::getContainer()->get('mautic.helper.paths')->getSystemPath('root', true).'/config/blacklist.php';
        if (file_exists($configPathTo)) {
            unlink($configPathTo);
        }
    }
}
