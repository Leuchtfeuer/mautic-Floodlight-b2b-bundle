<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnrichmentFloodlightCompanyCommand extends ModeratedCommand
{
    public const DATE_DEFAULT = '1970-01-01 00:00:00';

    public function __construct(
        protected PathsHelper $pathsHelper,
        protected CoreParametersHelper $coreParametersHelper,
        protected CompanyModel $companyModel,
        protected FloodlightCompanyDomainsModel $flCompanyDomainsModel,
        protected Config $config,
        protected FloodlightService $flooglightService,
        protected FieldHelper $fieldHelper,
        protected FloodlightLeadDomainsModel $floodlightLeadDomainsModel,
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this->setName('leuchtfeuer:floodlight:enrich')
            ->addOption(
                'by-address',
                'a',
                null,
                'Enrich company data by address'
            )
            ->addOption(
                'by-domain',
                'd',
                null,
                'Enrich company data by domain'
            )
            ->setDescription('Enrich company data with Floodlight data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isConfigured() || !$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not published</error>');

            return 0;
        }

        if (!$this->config->isProTier()) {
            $output->writeln('<error>Plugin isn\'t pro tier</error>');

            return 0;
        }

        if ($input->getOption('by-address')) {
            $greetInput = new ArrayInput([
                // the command name is passed as first argument
                'command' => 'leuchtfeuer:floodlight:enrich:address',
            ]);
            $greetInput->setInteractive(false);
            $returnCode = $this->getApplication()->doRun($greetInput, $output);

            return 0;
        }

        if ($input->getOption('by-domain')) {
            $greetInput = new ArrayInput([
                // the command name is passed as first argument
                'command' => 'leuchtfeuer:floodlight:enrich:domain',
            ]);
            $greetInput->setInteractive(false);
            $returnCode = $this->getApplication()->doRun($greetInput, $output);

            return 0;
        }

        return 1;
    }
}
