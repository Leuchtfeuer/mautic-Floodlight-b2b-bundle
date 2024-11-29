<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class FloodlightSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IpLookupRulesHelper $ipLookupRulesHelper,
        private Config $config,
        private FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel,
        private FloodlightLeadDomainsModel $floodlightLeadDomainsModel,
        private FloodlightService $flooglightService,
        private LoggerInterface $logger,
        private FieldHelper $fieldHelper,
        private CompanyModel $companyModel
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

        $companyDomains = $this->saveDomain($event);
        $request        = $event->getRequest();
        if (null !== $companyDomains && $this->config->isProTier()) {
            foreach ($companyDomains as $companyDomain) {
                assert($request instanceof Request);
                $this->saveIpLookup($request->server->get('REMOTE_ADDR'), $companyDomain);
            }
        }
    }

    /**
     * @return array<FloodlightCompanyDomains>|null
     */
    private function saveDomain(PageHitEvent $event): ?array
    {
        $request = $event->getRequest();
        assert($request instanceof Request);
        $dataDomain     = $this->ipLookupRulesHelper->getDomain($request->server->get('REMOTE_ADDR'));
        if (!$dataDomain) {
            return null;
        }
        $result           = $this->ipLookupRulesHelper->evaluate($event->getHit(), $dataDomain);
        if (
            IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE === $result['status']
            || IpLookupRulesHelper::LEAD_DOMAINS_STATUS_BLACKLISTED === $result['status']
        ) {
            $this->logger->error('Domain is blacklisted or failed to evaluate: '.$dataDomain['status']);

            return null;
        }

        return $this->floodlightCompanyDomainsModel->save($dataDomain['domain'], $event->getHit()->getLead(), $dataDomain['details']);
    }

    private function saveIpLookup(string $ip, FloodlightCompanyDomains $companyDomains): void
    {
        $company = $companyDomains->getCompany();
        if (
            !$this->ipLookupRulesHelper->isIp($ip)
            || !$this->floodlightLeadDomainsModel->getRepositoryFloodlightCompanyDomains()->isNew($companyDomains->getDomain())
        ) {
            return;
        }

        $data           = $this->flooglightService->get($ip);
        if (false === $data) {
            $this->logger->error('Floodlight API failed to return data for IP: '.$ip);

            return;
        }

        $floodlightDTO    = new FloodlightDTO($data);
        $leadDomainLookup = $floodlightDTO->fillLeadDomainLookUp();
        $this->floodlightLeadDomainsModel->saveEntity($leadDomainLookup);
        $this->logger->debug('fillLeadDomainLookUp data for IP: '.$leadDomainLookup->getIp().' was successfully retrieved with id'.$leadDomainLookup->getId());
        $this->fieldHelper->updateProperties($floodlightDTO);
        $this->logger->debug('Updating fields values properties for IP: ');
        $company       = $floodlightDTO->fillCompany($company);
        $this->logger->debug('Company data for IP: '.$company->getName().' was successfully retrieved with id '.$company->getId());
        $this->companyModel->saveEntity($company);
    }
}
