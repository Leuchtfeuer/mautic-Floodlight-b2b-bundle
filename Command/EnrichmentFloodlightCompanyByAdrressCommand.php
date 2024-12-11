<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

class EnrichmentFloodlightCompanyByAdrressCommand extends ModeratedCommand
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
        $this->setName('leuchtfeuer:floodlight:enrich:address')
            ->setDescription('Enrich company data with Floodlight data by address');
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

        // Get all companies that need to be enriched, not we aren't get data come from others sources
        $leadDomains = $this->floodlightLeadDomainsModel->getLeadDomainToEnrichement();

        if (empty($leadDomains)) {
            $output->writeln('<info>No companies to enrich</info>');

            return 0;
        }

        foreach ($leadDomains as $leadDomain) {
            $ipHash = $leadDomain->getIpHash();
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('<comment>===============================================================</comment>');
                $output->writeln('<comment> IP hash: '.$ipHash.'</comment>');
                $output->writeln('<comment>===============================================================</comment>');
            }

            $result = $this->flooglightService->getByIpHash($ipHash);
            if (Response::HTTP_TOO_MANY_REQUESTS === $result['statusRequestCode']) {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    // Create tests for this
                    $output->writeln('<error>Too many requests</error>');
                    $output->writeln('<error>Request Exceed </error>');

                    return 0;
                }
            }

            $lead = $leadDomain->getContact();

            if (Response::HTTP_NOT_FOUND === $result['statusRequestCode']) {
                $leadFailed = $this->floodlightLeadDomainsModel->getRepository()->findOneBy(
                    [
                        'contact' => $lead,
                        'domain'  => IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE,
                    ]
                );

                if (!empty($leadFailed)) {
                    assert($leadFailed instanceof FloodlightLeadDomains);
                    $leadFailed->setCount($leadFailed->getCount() + 1);
                    $this->floodlightLeadDomainsModel->saveEntity($leadFailed);
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln('<error>Lead domain of Lead '.$lead->getName().' with request 404 counting fail</error>');
                    }
                    continue;
                }

                $leadDomain->setDomain(IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE);
                $leadDomain->setCount(1);
                $this->floodlightLeadDomainsModel->saveEntity($leadDomain);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Lead domain of Lead '.$lead->getName().' with request 404</error>');
                }
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && empty($leadDomain->getDateModified())) {
                $leadDomain->setDateModified((new \DateTime(self::DATE_DEFAULT))->format('Y-m-d H:i:s'));
                $this->floodlightLeadDomainsModel->saveEntity($leadDomain);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<info>Lead domain '.$leadDomain->getDomain().' with request 202 by second time</info>');
                }
                continue;
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && $leadDomain->getDateModified() === (new \DateTime(self::DATE_DEFAULT))->format('Y-m-d H:i:s')) {
                $leadDomain->setDateModified((new \DateTime())->format('Y-m-d H:i:s'));
                $this->floodlightLeadDomainsModel->saveEntity($leadDomain);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Lead domain '.$leadDomain->getDomain().' with request 202 by third time</error>');
                }
                continue;
            }

            if (Response::HTTP_OK !== $result['statusRequestCode']) {
                $output->writeln('<error>Error '.$lead->getName().' status  '.$result['statusRequestCode'].'</error>');
                $output->writeln('<comment>-----------------------------------------------------------------</comment>');

                continue;
            }

            $floodlightDTO = new FloodlightDTO($result);
            $this->fieldHelper->updateProperties($floodlightDTO);
            $domain = $floodlightDTO->getCompanyPrimaryDomain();
            if(empty($domain) && !empty($floodlightDTO->getDomains())){
                $domain = $floodlightDTO->getDomains()[0];
            }

            if (empty($domain)) {
                continue;
            }

            $floodlightCompanyDomains = $this->flCompanyDomainsModel->getRepository()->findBy(
                [
                    'domain' => $domain,
                ]
            );

            if(empty($floodlightCompanyDomains)){
                $company = $this->companyModel->getEntity();
                $company = $floodlightDTO->fillCompany($company);
                $this->companyModel->saveEntity($company);
                $floodlightCompanyDomains = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
                $this->flCompanyDomainsModel->saveEntities($floodlightCompanyDomains);
                continue;
            }else{
                foreach ($floodlightCompanyDomains as $floodlightCompanyDomain) {
                    $company = $floodlightCompanyDomain->getCompany();
                    $company = $floodlightDTO->fillCompany($company);
                    $this->companyModel->saveEntity($company);

                    $floodlightCompanyDomains = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
                    $this->flCompanyDomainsModel->saveEntities($floodlightCompanyDomains);
                }
            }

            $leadDomain->setDomain($domain);
            $leadDomain->setCount($leadDomain->getCount() + 1);
            $leadDomain->setDateModified(new \DateTime());
            $this->floodlightLeadDomainsModel->saveEntity($leadDomain);

            $output->writeln('<info>Company data updated</info>');

            $output->writeln('<info>Company(s) domain(s) data updated</info>');
            $output->writeln(PHP_EOL);
        }

        $output->writeln('<info>Enriching company data DONE!</info>');

        return 0;
    }
}
