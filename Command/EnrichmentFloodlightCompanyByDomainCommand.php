<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
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

class EnrichmentFloodlightCompanyByDomainCommand extends ModeratedCommand
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
        $this->setName('leuchtfeuer:floodlight:enrich:domain')
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

            if (Response::HTTP_NOT_FOUND === $result['statusRequestCode']) {
                $lead = $leadDomain->getContact();

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

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && empty($leadDomain->getEnrichmentDate())) {
                $leadDomain->setEnrichmentDate(new \DateTime(self::DATE_DEFAULT));
                $this->floodlightLeadDomainsModel->saveEntity($leadDomain);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<info>Lead domain '.$leadDomain->getDomain().' with request 202 by second time</info>');
                }
                continue;
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && (new \DateTime(self::DATE_DEFAULT)) == $leadDomain->getEnrichmentDate()) {
                $leadDomain->setEnrichmentDate(new \DateTime());
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
            $domain  = $floodlightDTO->getCompanyPrimaryDomain();
            if (empty($domain) && !empty($floodlightDTO->getDomains())) {
                $domain = $floodlightDTO->getDomains()[0];
            }
            if (!empty($domain)) {
                $floodlightCompanyDomains = $this->flCompanyDomainsModel->getRepository()->findBy(['domain' => $domain]);
                if (!empty($floodlightCompanyDomains)) {
                    foreach ($floodlightCompanyDomains as $floodlightCompanyDomain) {
                        $company = $floodlightCompanyDomain->getCompany();
                        $company = $floodlightDTO->fillCompany($company);
                        $this->companyModel->saveEntity($company);
                        $tempResult = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
                        $result     = array_merge($result, $tempResult);
                    }
                }
                $output->writeln('<error>Domain is blacklisted</error>');
                continue;
            }

            $company = $lead->getPrimaryCompany();
            $company = $floodlightDTO->fillCompany($company);
            $this->companyModel->saveEntity($company);

            $floodlightCompanyDomains = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
            if (!empty($floodlightCompanyDomains)) {
                $this->flCompanyDomainsModel->saveEntities($floodlightCompanyDomains);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    foreach ($floodlightCompanyDomains as $floodlightCompanyDomain) {
                        $output->writeln('Company domain data for IP: '.$company->getName().' was successfully retrieved with id '.$floodlightCompanyDomain->getId());
                    }
                }
            }

            $leadDomainLookup = $floodlightDTO->fillLeadDomainLookUp();
            $this->floodlightLeadDomainsModel->saveEntity($leadDomainLookup);
            $lead = $leadDomain->getContact();

            $output->writeln('<info>Company data updated</info>');

            $output->writeln('<info>Company(s) domain(s) data updated</info>');
            $output->writeln(PHP_EOL);
        }

        $output->writeln('<info>Enriching company data DONE!</info>');

        return 0;
    }
}
