<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
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

class EnrichmentFloodlightCompanyByAddressCommand extends ModeratedCommand
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

        $leadDomains = $this->floodlightLeadDomainsModel->getLeadDomainToEnrichement();

        if (empty($leadDomains)) {
            $output->writeln('<info>No companies to enrich</info>');

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

            $result = $this->flooglightService->get($ipHash);

            // test it
            if (is_bool($result)) {
                $output->writeln('<error>Lead domain '.$leadDomain->getDomain().' without request code</error>');
                continue;
            }

            if (Response::HTTP_TOO_MANY_REQUESTS === $result['statusRequestCode']) {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    // Create tests for this
                    $output->writeln('<error>Too many requests</error>');
                    $output->writeln('<error>Request Exceed </error>');

                    return 0;
                }
            }

            $lead = $leadDomain->getContact();

            // test it
            if (null === $lead) {
                $output->writeln('<error>Lead domain '.$leadDomain->getDomain().' without lead</error>');
                continue;
            }

            if (Response::HTTP_NOT_FOUND === $result['statusRequestCode']) {
                //Delete entry
                //Schauen, ob bereits so ein entry existiert
                //Erstellen oder updaten
                //$leadDomain->setDomain(FloodlightLeadDomains::STATUS_NO_DATA_FOUND);
                $leadDomain->setStatus(FloodlightLeadDomains::STATUS_NO_DATA_FOUND);
                $leadDomain->setDateLastLookup(new \DateTime());
                $leadDomain->setCount($leadDomain->getCount() + 1);
                $this->floodlightLeadDomainsModel->saveEntity($leadDomain);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Lead domain of Lead '.$lead->getName().' with request 404</error>');
                }
            }

            if (
                Response::HTTP_ACCEPTED === $result['statusRequestCode']
                && empty($leadDomain->getDateLastLookup())
            ) {
                $leadDomain->setDateLastLookup(new \DateTime(self::DATE_DEFAULT));
                $this->floodlightLeadDomainsModel->saveEntity($leadDomain);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<info>Lead domain '.$leadDomain->getDomain().' with request 202 by second time</info>');
                }
                continue;
            }

            if (
                Response::HTTP_ACCEPTED === $result['statusRequestCode']
                && $leadDomain->getDateLastLookup() === (new \DateTime(self::DATE_DEFAULT))
            ) {
                $leadDomain->setDateLastLookup(new \DateTime());
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
            if (empty($domain) && !empty($floodlightDTO->getDomains())) {
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

            if (empty($floodlightCompanyDomains)) {
                $company = $this->companyModel->getEntity();
                $company = $floodlightDTO->fillCompany($company);
                $this->companyModel->saveEntity($company);
                $floodlightCompanyDomains = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
                $this->flCompanyDomainsModel->saveEntities($floodlightCompanyDomains);
            } else {
                foreach ($floodlightCompanyDomains as $floodlightCompanyDomain) {
                    assert($floodlightCompanyDomain instanceof FloodlightCompanyDomains);
                    $company = $floodlightCompanyDomain->getCompany();
                    $company = $floodlightDTO->fillCompany($company);
                    $this->companyModel->saveEntity($company);
                    $floodlightCompanyDomains = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
                    $this->flCompanyDomainsModel->saveEntities($floodlightCompanyDomains);
                }
            }
            $leadDomain->setDomain($domain);
            $leadDomain->setCount($leadDomain->getCount() + 1);
            $leadDomain->setDateLastLookup(new \DateTime());
            $this->floodlightLeadDomainsModel->saveEntity($leadDomain);

            $output->writeln('<info>Company data updated</info>');

            $output->writeln('<info>Company(s) domain(s) data updated</info>');
            $output->writeln(PHP_EOL);
        }

        $output->writeln('<info>Enriching company data DONE!</info>');

        return 0;
    }
}
