<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

class EnrichmentFloodlightCompanyCommand extends ModeratedCommand
{
    public function __construct(
        protected PathsHelper $pathsHelper,
        protected CoreParametersHelper $coreParametersHelper,
        protected CompanyModel $companyModel,
        protected FloodlightCompanyDomainsModel $flCompanyDomainsModel,
        protected Config $config,
        protected FloodlightService $flooglightService,
        protected FieldHelper $fieldHelper
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this->setName('leuchtfeuer:floodlight:enrich')
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

        $companies = $this->getCompaniesToEnrich();
        if (empty($companies)) {
            $output->writeln('<info>No companies to enrich</info>');

            return 0;
        }

        foreach ($companies as $company) {
            $flCompanyDomain = $this->flCompanyDomainsModel->getRepository()->findOneBy(
                [
                    'company'         => $company->getId(),
                    'isPrimaryDomain' => true,
                ]
            );

            if (!empty($flCompanyDomain)) {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('<comment>===============================================================</comment>');
                    $output->writeln('<comment> Company: '.$flCompanyDomain->getCompany()->getName().'</comment>');
                    $output->writeln('<comment> Domain: '.$flCompanyDomain->getDomain().'</comment>');
                    $output->writeln('<comment>===============================================================</comment>');
                }
                $this->update($flCompanyDomain, $output);
            } else {
                $output->writeln('<comment>===============================================================</comment>');
                $output->writeln('<comment>***Fail*** Company domain: '.$company->getName().' not found.</comment>');
                $output->writeln('<comment>===============================================================</comment>');
            }

            $output->writeln(PHP_EOL);
        }

        $output->writeln('<info>Enriching company data DONE!</info>');

        return 0;
    }

    private function update(FloodlightCompanyDomains $flcompanyDomain, OutputInterface $output): void
    {
        $output->writeln('<comment>Enriching company data with Floodlight</comment>');
        $result        = $this->flooglightService->get($flcompanyDomain->getDomain());
        if (Response::HTTP_OK !== $result['status'] && Response::HTTP_BAD_REQUEST !== $result['status']) {
            $output->writeln('<error>Error status '.$result['status'].'</error>');
            $output->writeln('<comment>-----------------------------------------------------------------</comment>');

            return;
        }

        $company       = $this->companyModel->getEntity($flcompanyDomain->getCompany()->getId());
        if (!$company instanceof Company) {
            $output->writeln('<error>Company not found</error>');
            $output->writeln('<comment>-----------------------------------------------------------------</comment>');

            return;
        }
        if (Response::HTTP_BAD_REQUEST === $result['status']) {
            $company->addUpdatedField('enrichment_date', \date('Y-m-d H:i:s'));
            $this->companyModel->saveEntity($company);
            $output->writeln('<error>Error bad request but data was updated</error>');
            $output->writeln('<comment>-----------------------------------------------------------------</comment>');

            return;
        }
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $output->writeln(json_encode($result['content'], JSON_PRETTY_PRINT));
        }

        $floodlightDTO = new FloodlightDTO($result);
        $this->fieldHelper->updateProperties($floodlightDTO);
        $company       = $floodlightDTO->fillCompany($company);
        $this->companyModel->saveEntity($company);
        $output->writeln('<info>Company data updated</info>');

        $companiesDomain = $floodlightDTO->fillCompaniesDomain($this->flCompanyDomainsModel, $company);
        if (!empty($companiesDomain)) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('<comment>-----------------------------------------------------------------</comment>');
                $output->writeln('<comment>Adding company domains in database</comment>');
            }
            $this->flCompanyDomainsModel->saveEntities($companiesDomain);
            $domains = $this->getDomains($companiesDomain);
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('<info>Domains added: '.\implode(PHP_EOL.'- ', $domains).'</info>');
            }
        }
        $output->writeln('<comment>-----------------------------------------------------------------</comment>');
    }

    /**
     * @param array<FloodlightCompanyDomains> $flCompaniesDomain
     *
     * @return array<string>
     */
    private function getDomains(array $flCompaniesDomain): array
    {
        $domains = [];
        foreach ($flCompaniesDomain as $flCompanyDomain) {
            $domains[] = $flCompanyDomain->getDomain();
        }

        return $domains;
    }

    /**
     * @return array<Company>|null
     */
    private function getCompaniesToEnrich(): ?array
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
