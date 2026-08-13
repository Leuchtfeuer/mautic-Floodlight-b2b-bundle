<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDtoFactory;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\DtoHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status200Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Mapping\CompanyMapper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

#[AsCommand(name: 'leuchtfeuer:floodlight:enrich-companies', description: 'Enrich company data with Floodlight data by address')]
class EnrichCompanyCommand extends ModeratedCommand
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
        protected FloodlightDtoFactory $floodlightDtoFactory,
        protected DtoHelper $dtoHelper,
        protected Status200Strategy $status200Strategy,
        protected CompanyMapper $companyMapper,
        private readonly \Mautic\LeadBundle\Entity\CompanyRepository $companyRepository,
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isPublished()) {
            $output->writeln('<error>Plugin is not published</error>');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        if (!$this->config->isConfigured()) {
            $output->writeln('<error>There is no API key set for the plugin</error>');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        $this->flCompanyDomainsModel->saveDomainsForNonFloodlightCompanies();

        $companyDomains = $this->flCompanyDomainsModel->getCompanyDomainToEnrichment();
        if (empty($companyDomains) || !is_array($companyDomains)) {
            $output->writeln('<info>No companies to enrich</info>');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        if ($this->config->checkIfLookupReturned429ResponseInLast60Minutes()) {
            $output->writeln('<error>Floodlight quota exceeded, please try again later</error>');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        $pendingCompanyIds = [];
        foreach ($companyDomains as $companyDomain) {
            assert($companyDomain instanceof FloodlightCompanyDomains);
            $domain                = $companyDomain->getDomain();
            // use getEntity to make sure that also custom fields are loaded
            $company               = $this->companyRepository->getEntity($companyDomain->getCompany()->getId()) ?? $companyDomain->getCompany();
            $companyEnrichmentDate = $company->getFieldValue('f4b_enrichment_date');
            if ('' === trim($domain)) {
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
                $this->companyModel->saveEntity($company);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Domain not found (404) : '.$companyDomain->getDomain().'</error>');
                }
                continue;
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && empty($companyEnrichmentDate)) {
                $pendingCompanyIds[$company->getId()] = true;
                $company->addUpdatedField('f4b_enrichment_date', (new \DateTime(self::DATE_DEFAULT))->format('Y-m-d H:i:s'));
                $this->companyModel->saveEntity($company);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<info>Company domain '.$companyDomain->getDomain().' with request 202, enrichment date set to default date</info>');
                }
                continue;
            }

            if (Response::HTTP_ACCEPTED === $result['statusRequestCode'] && (new \DateTime(self::DATE_DEFAULT))->format('Y-m-d H:i:s') == $companyEnrichmentDate) {
                $pendingCompanyIds[$company->getId()] = true;
                $company->addUpdatedField('f4b_enrichment_date', (new \DateTime())->format('Y-m-d H:i:s'));
                $this->companyModel->saveEntity($company);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<error>Lead domain '.$companyDomain->getDomain().' with request 202, enrichment date set to current date</error>');
                }
                continue;
            }

            if (Response::HTTP_TOO_MANY_REQUESTS === $result['statusRequestCode']) {
                $output->writeln('<error>Floodlight quota exceeded</error>');
                $this->config->setLast429Timestamp();
                continue;
            }

            if (Response::HTTP_OK !== $result['statusRequestCode']) {
                $this->flCompanyDomainsModel->saveEntity($companyDomain);
                $output->writeln('<error>Error '.$companyDomain->getDomain().' status  '.$result['statusRequestCode'].'</error>');
                $output->writeln('<comment>-----------------------------------------------------------------</comment>');

                continue;
            }

            $floodlightDTO = $this->floodlightDtoFactory->create($result);
            $floodlightDTO = $this->dtoHelper->cleanDtoFromNonFloodlightUpdatableFields($floodlightDTO, $company);
            $this->fieldHelper->updateProperties($floodlightDTO);

            $company = $this->companyMapper->mapToCompany($floodlightDTO, $company);
            $this->status200Strategy->processCompanyLogo($company, $floodlightDTO->getCompanyLogoUrl());
            $this->companyModel->saveEntity($company);
            if (
                false === $this->config->getIgnoreTelcosSettings()
                || $this->status200Strategy->companyHasKnownContacts($company)
                || 'Telecommunications' !== $floodlightDTO->getCompanyIndustryICBCSector()
            ) {
                $this->flCompanyDomainsModel->saveEntity($companyDomain);
                $this->status200Strategy->updateRegularCompanyDomains($floodlightDTO, $company);
            } else {
                $this->status200Strategy->detachAllUnknowContactsFromCompany($company);
                $this->companyModel->saveEntity($company);
                $this->status200Strategy->updateTelcoCompanyDomains($floodlightDTO, $company);
            }

            $output->writeln('<info>Company data updated '.$company->getName().'</info>');
            $output->writeln('<info>CompanyDomain '.$companyDomain->getDomain().' data updated</info>');
            $output->writeln(PHP_EOL);
        }

        $pendingCount = count($pendingCompanyIds);
        if ($pendingCount > 0) {
            $output->writeln('<info>'.$pendingCount.' '.(1 === $pendingCount ? 'company' : 'companies').' still pending enrichment results.</info>');
        }

        $output->writeln('<info>Enriching company data DONE!</info>');

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
