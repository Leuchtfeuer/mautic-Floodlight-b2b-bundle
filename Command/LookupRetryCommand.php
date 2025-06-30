<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LookupRetryCommand extends ModeratedCommand
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
        protected IpLookupRulesHelper $ipLookupRulesHelper,
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this->setName('leuchtfeuer:floodlight:lookup-retries')
            ->setDescription('Retry lookups with status retry');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not published</error>');

            return 0;
        }

        if (!$this->config->isConfigured()) {
            $output->writeln('<error>There is no API key set for the plugin</error>');

            return 0;
        }

        $leadDomains = $this->floodlightLeadDomainsModel->getLeadDomainToEnrichement();

        if (empty($leadDomains)) {
            $output->writeln('<info>No lookups to retry found</info>');

            return 0;
        }

        foreach ($leadDomains as $leadDomain) {
            assert($leadDomain instanceof FloodlightLeadDomains);
            $ipHash = $leadDomain->getIpHash();
            if (null === $ipHash) {
                $output->writeln('<error>Lead domain '.$leadDomain->getDomain().' without IP hash</error>');

                continue;
            }
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('<comment>===============================================================</comment>');
                $output->writeln('<comment> IP hash: '.$ipHash.'</comment>');
                $output->writeln('<comment>===============================================================</comment>');
            }

            $this->ipLookupRulesHelper->setChannel($output);

            $lead = $leadDomain->getContact();
            \assert($lead instanceof Lead);
            $this->ipLookupRulesHelper->getAndProcessFloodlightApiData($ipHash, $lead);
        }
        $output->writeln('<info>Retry for all corresponding lookups done</info>');

        return 0;
    }
}
