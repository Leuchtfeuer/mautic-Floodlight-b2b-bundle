<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Reporter
{
    private LoggerInterface|OutputInterface $channel;

    public function notify(string $message, int $level = 0): void
    {
        if ($this->channel instanceof LoggerInterface) {
            $this->channel->error($message);
        } elseif ($this->channel instanceof OutputInterface) {
            $verbosity =  $this->channel->getVerbosity();
            if ($verbosity == $level) {
                $this->channel->writeln($message);
            }
        }
    }

    public function setChannel(LoggerInterface|OutputInterface $channel): void
    {
        $this->channel = $channel;
    }
}
