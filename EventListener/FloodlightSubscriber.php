<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class FloodlightSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IpLookupRulesHelper $ipLookupRulesHelper,
        private Config $config,
        private FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel,
        private LoggerInterface $logger,
        private FieldHelper $fieldHelper,
        private CompanyModel $companyModel,
        private LeadModel $leadModel,
        private readonly FloodlightLeadDomainsModel $floodlightLeadDomainsModel
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageEvents::PAGE_ON_HIT => ['onPageHit', 0],
        ];
    }

    public function onPageHit(PageHitEvent $event): void
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured()) {
            return;
        }
        $this->execute($event);
    }

    private function execute(PageHitEvent $event): void
    {
        $isValid = $this->ipLookupRulesHelper->check($event->getHit());

        if (false === $isValid) {
            return;
        }

        $this->saveDomains($event);
    }

    private function saveDomains(PageHitEvent $event): void
    {
        $request = $event->getRequest();
        assert($request instanceof Request);
        $remoteAddr = $request->server->get('REMOTE_ADDR');

        $this->ipLookupRulesHelper->setChannel($this->logger);

        if (empty($remoteAddr)) {
            //@TODO no ip for page hit - this should be notified
            $this->ipLookupRulesHelper->notifyNoDataFromGetDomain();
        }

        $dataDomain     = $this->ipLookupRulesHelper->getAndProcessFloodlightApiData($remoteAddr, $event->getHit()->getLead());
        if (!$dataDomain)
        {
            //it would be nice to se what was in the response whet it is not any standard
            $this->ipLookupRulesHelper->notifyNoDataFromGetDomain();
        }
        else
        {
            $this->ipLookupRulesHelper->processFloodlightLeadDomains($event->getHit()->getLead(), $dataDomain);
        }
    }

    /**
     * @param array<string, mixed>            $dataDomain
     * @param array<FloodlightCompanyDomains> $floodCompanyDomains
     *
     * @return array<FloodlightCompanyDomains>
     */
    private function updateCompanyCompanyDomains(array $dataDomain, array $floodCompanyDomains, Lead $lead): array
    {
        $result = [];
        if (
            isset($dataDomain['details']['floodlight'])
            && $dataDomain['details']['floodlight'] instanceof FloodlightDTO
        ) {
            $floodlightDTO = $dataDomain['details']['floodlight'];
            assert($floodlightDTO instanceof FloodlightDTO);
            $this->fieldHelper->updateProperties($floodlightDTO);
            foreach ($floodCompanyDomains as $companyDomain) {
                assert($companyDomain instanceof FloodlightCompanyDomains);
                $company = $companyDomain->getCompany();
                $company = $floodlightDTO->fillCompany($company);
                $this->companyModel->saveEntity($company);
                $tempResult = $floodlightDTO->fillCompaniesDomain($this->floodlightCompanyDomainsModel, $company);
                $result     = array_merge($result, $tempResult);
            }
            if (!empty($result)) {
                $this->floodlightCompanyDomainsModel->saveEntities($result);
            }

            return $result;
        }

        foreach ($floodCompanyDomains as $companyDomain) {
            assert($companyDomain instanceof FloodlightCompanyDomains);
            $this->leadModel->addToCompany($lead, $companyDomain->getCompany());
            $result[] = $companyDomain;
        }
        if (!empty($result)) {
            $this->floodlightCompanyDomainsModel->saveEntities($result);

            return $result;
        }

        return [];
    }
}
