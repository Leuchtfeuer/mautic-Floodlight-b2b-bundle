<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;

use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DomainBlacklistHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;

final class DomainBlacklistHelperTest extends MauticMysqlTestCase
{
    private DomainBlacklistHelper $domainBlackList;
    private string $domain;

    public function setUp(): void
    {
        parent::setUp();

        $configPathTo    = self::getContainer()->get(PathsHelper::class)->getSystemPath('root', true).'/config/f4b_domain_blacklist.php';
        $pluginPathFrom  = self::getContainer()->get(PathsHelper::class)->getSystemPath('plugins', true).'/'.LeuchtfeuerFloodlightB2BIntegration::INTEGRATION_NAME.'Bundle/Config/f4b_domain_blacklist.php';
        if (!file_exists($configPathTo)) {
            copy($pluginPathFrom, $configPathTo);
        }

        $pathsHelper = self::getContainer()->get(PathsHelper::class);
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
        $this->assertNotContains($this->domain, $this->domainBlackList->get());
        $this->domainBlackList->add($this->domain);
        $this->assertContains($this->domain, $this->domainBlackList->get());
        $this->domainBlackList->remove($this->domain);
    }

    public function testRemoveDomain(): void
    {
        $this->domainBlackList->add($this->domain);
        $this->assertContains($this->domain, $this->domainBlackList->get());
        $this->domainBlackList->remove($this->domain);
        $this->assertNotContains($this->domain, $this->domainBlackList->get());
    }
}
