<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;


use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Reporter;
use Psr\Log\LoggerInterface;
class ReporterTest extends TestCase{


    public function testMessageFromCommand(){


        $channel = $this->createMock(OutputInterface::class);

        $channel->expects($this->once()) // Or ->any(), depending on what you want
        ->method('writeln')
            ->with('someText');

        $reporter = new Reporter($channel);

        $reporter->notify('someText');
        $this->assertTrue(true);

    }

    public function testMessageFromCommandWithVerbosity()
    {
        $channel = $this->createMock(OutputInterface::class);

        $channel->expects($this->once())
        ->method('writeln')
            ->with('someText');

        $channel->expects($this->once())
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_VERBOSE);

        $report = new Reporter($channel);

        $report->notify('someText',OutputInterface::VERBOSITY_VERBOSE);
        $this->assertTrue(true);
    }

    public function testLoggingWithLogger()
    {
        $channel = $this->createMock(LoggerInterface::class);

        $channel->expects($this->once()) // Or ->any(), depending on what you want
        ->method('error')
            ->with('someText');

        $reporter = new Reporter($channel);

        $reporter->notify('someText');
        $this->assertTrue(true);
    }

}