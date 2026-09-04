<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FreeEmailDomainHelper;
use PHPUnit\Framework\TestCase;

final class FreeEmailDomainHelperTest extends TestCase
{
    private FreeEmailDomainHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new FreeEmailDomainHelper();
    }

    public function testKnownFreeProviderIsDetected(): void
    {
        self::assertTrue($this->helper->isFreemailer('gmail.com'));
    }

    public function testDetectionIsCaseInsensitive(): void
    {
        self::assertTrue($this->helper->isFreemailer('GMail.Com'));
    }

    public function testBusinessDomainIsNotDetected(): void
    {
        self::assertFalse($this->helper->isFreemailer('leuchtfeuer.com'));
    }
}
