<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnrichmentFloodlightCompanyCommand extends ModeratedCommand
{
    public function __construct(
        protected PathsHelper                   $pathsHelper,
        protected CoreParametersHelper          $coreParametersHelper,
        protected CompanyModel                  $companyModel,
        protected FloodlightCompanyDomainsModel $companyDomainsModel,
        protected Config                        $config,
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this->setName('leuchtfeuer:flood:enrich')
            ->setDescription('Enrich company data with ABM data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isConfigured() || !$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not configured</error>');

            return 1;
        }

        if(!$this->config->isProTier()){
            $output->writeln('<info>Plugin is pro tier</info>');
            return 1;
        }

        $companies = $this->getCompaniesToEnrich();
        if (empty($companies)) {
            $output->writeln('<info>No companies to enrich</info>');

            return 0;
        }


        return 0;
    }

    /**
     * @return array<Company>|array
     */
    private function getCompaniesToEnrich(): array
    {
        return $this->companyModel->getRepository()->getEntitiesWithCustomFields(
            'company',
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'enrichment_date',
                            'expr'   => 'isNull',
                        ],
                    ],
                ],
            ]
        );
    }
}
