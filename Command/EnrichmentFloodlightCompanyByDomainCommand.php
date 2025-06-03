<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
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

        $this->flCompanyDomainsModel->saveDomainsForNonFloodlightCompanies();

        $companyDomains = $this->flCompanyDomainsModel->getCompanyDomainToEnrichment();
        if (empty($companyDomains) || !is_array($companyDomains)) {
            $output->writeln('<info>No companies to enrich</info>');

            return 0;
        }

        foreach ($companyDomains as $companyDomain) {
            assert($companyDomain instanceof FloodlightCompanyDomains);
            $domain = $companyDomain->getDomain();
            $company = $companyDomain->getCompany();
            $companyEnrichmentDate = $company->getFieldValue('f4b_enrichment_date');
            if (null === $domain) {
                continue;
            }

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('<comment>===============================================================</comment>');
                $output->writeln('<comment> Domain : '.$companyDomain->getDomain().'</comment>');
                $output->writeln('<comment>===============================================================</comment>');
            }
            $result = $this->flooglightService->getByDomain($domain);

            if (is_bool($result)) {
                $output->writeln('<error>Domain '.$companyDomain->getDomain().' without statusRequestCode</error>');
                continue;
            }

            if (Response::HTTP_UNAUTHORIZED === $result['statusRequestCode']) {
                $output->writeln('<error>You are using an invalid API key</error>');
                continue;
            }


            if (Response::HTTP_NOT_FOUND === $result['statusRequestCode']) {
                $company->addUpdatedField('f4b_enrichment_date', (new \DateTime())->format('Y-m-d H:i:s'));
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Domain not found (404) : '.$companyDomain->getDomain().'</error>');
                }
                continue;
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && empty($companyEnrichmentDate)) {
                $company->addUpdatedField('f4b_enrichment_date', (new \DateTime(self::DATE_DEFAULT))->format('Y-m-d H:i:s'));
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<info>Company domain '.$companyDomain->getDomain().' with request 202, enrichment date set to default date</info>');
                }
                continue;
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && (new \DateTime(self::DATE_DEFAULT))->format('Y-m-d H:i:s') == $companyEnrichmentDate) {
                $company->addUpdatedField('f4b_enrichment_date', (new \DateTime())->format('Y-m-d H:i:s'));
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Lead domain '.$companyDomain->getDomain().' with request 202, enrichment date set to current date</error>');
                }
                continue;
            }

            if (Response::HTTP_OK !== $result['statusRequestCode']) {
                $this->flCompanyDomainsModel->saveEntity($companyDomain);
                $output->writeln('<error>Error '.$companyDomain->getDomain().' status  '.$result['statusRequestCode'].'</error>');
                $output->writeln('<comment>-----------------------------------------------------------------</comment>');

                continue;
            }

            $floodlightDTO = new FloodlightDTO($result);
            $this->fieldHelper->updateProperties($floodlightDTO);
            $company = $floodlightDTO->fillCompany($company);
            $this->companyModel->saveEntity($company);

            $this->flCompanyDomainsModel->saveEntity($companyDomain);

            $output->writeln('<info>Company data updated ' . $company->getName() . '</info>');
            $output->writeln('<info>CompanyDomain ' . $companyDomain->getDomain() . ' data updated</info>');
            $output->writeln(PHP_EOL);
        }

        $output->writeln('<info>Enriching company data DONE!</info>');

        return 0;
    }
}
