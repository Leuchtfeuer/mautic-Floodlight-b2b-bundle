<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Psr\Log\LoggerInterface;

class WhoisPyService
{
    protected $whoisPyPath;
    protected $whoisPyPathFile;
    protected $whoisPyFile;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->whoisPyFile     = 'main.py';
        $this->whoisPyPath     = __DIR__.'/../Assets/scripts/';
        $this->whoisPyPathFile = __DIR__.'/../Assets/scripts/'.$this->whoisPyFile;
        $this->logger          = $logger;
    }

    public function getWhoisData(string $ip): array
    {
        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return [];
        }

        $output = $this->execute($ip);

        if (empty($output)) {
            $this->logger->error('WhoisPyService exec fail: '.$output);

            return [];
        }

        $output = $this->format($output);

        if (!$this->validate($output)) {
            $this->logger->error('WhoisPyService fail validate: '.$output);

            return [];
        }
        $jsonResult = json_decode($output, true);

        if (empty($jsonResult)) {
            $this->logger->error('WhoisPyService fail: '.$output);

            return [];
        }

        return $jsonResult;
    }

    private function validate(string $output): bool
    {
        if (str_contains($output, 'Error:')) {
            return false;
        }

        return true;
    }

    private function execute(string $ip): false|string|null
    {
        $command = 'python3 '.$this->whoisPyPathFile.' -ipaddr '.$ip;

        return shell_exec($command);
    }

    private function format(string $output): array|string
    {
        if (empty($output)) {
            return [];
        }

        $replace = [
            'from'=> [
                PHP_EOL,
                'False',
                'True',
                "'",
            ],
            'to'=> [
                '',
                'false',
                'true',
                '"',
            ],
        ];

        return str_replace($replace['from'], $replace['to'], $output);
    }
}
