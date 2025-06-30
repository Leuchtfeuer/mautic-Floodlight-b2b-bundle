<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services;

use Psr\Log\LoggerInterface;

class WhoisPyService
{
    protected string $whoisPyPath;
    protected string $whoisPyPathFile;
    protected string $whoisPyFile;

    public function __construct(protected LoggerInterface $logger)
    {
        $this->whoisPyFile     = 'main.py';
        $this->whoisPyPath     = __DIR__.'/../Assets/scripts/';
        $this->whoisPyPathFile = __DIR__.'/../Assets/scripts/'.$this->whoisPyFile;
    }

    /**
     * @return array<mixed>
     */
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
        $jsonResult = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

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

    public function execute(string $ip): false|string|null
    {
        $pluginDir         = __DIR__.'/..';
        $pythonModulesPath = $pluginDir.'/PythonModules';

        $envVar = '';
        if (is_dir($pythonModulesPath)) {
            $envVar = 'PYTHONPATH='.escapeshellarg($pythonModulesPath).' ';
        }

        $command = $envVar.'python3 '.escapeshellarg($this->whoisPyPathFile).' -ipaddr '.escapeshellarg($ip);

        return shell_exec($command);
    }

    private function format(string $output): string
    {
        if (empty($output)) {
            return '';
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
