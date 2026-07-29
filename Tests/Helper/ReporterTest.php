<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Reporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReporterTest extends TestCase
{
    public function testMessageFromCommand(): void
    {
        $channel = $this->createMock(OutputInterface::class);

        $channel->expects($this->once()) // Or ->any(), depending on what you want
        ->method('writeln')
            ->with('someText');

        $reporter = new Reporter();
        $reporter->setChannel($channel);

        $reporter->notify('someText');

    }

    public function testMessageFromCommandWithVerbosity(): void
    {
        $channel = $this->createMock(OutputInterface::class);

        $channel->expects($this->once())
        ->method('writeln')
            ->with('someText');

        $channel->expects($this->once())
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_VERBOSE);

        $reporter = new Reporter();
        $reporter->setChannel($channel);

        $reporter->notify('someText', OutputInterface::VERBOSITY_VERBOSE);

    }

    public function testLoggingWithLogger(): void
    {
        $channel = $this->createMock(LoggerInterface::class);

        $channel->expects($this->once()) // Or ->any(), depending on what you want
        ->method('error')
            ->with('someText');

        $reporter = new Reporter();
        $reporter->setChannel($channel);

        $reporter->notify('someText');

    }
}
