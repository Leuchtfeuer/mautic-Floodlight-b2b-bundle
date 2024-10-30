<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightIpDomainLookupsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper;
use Mautic\LeadBundle\Model\CompanyModel;

class FloodlightSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IpLookupRulesHelper $ipLookupRulesHelper,
        private Config $config,
        private FloodlightCompanyDomainsModel $floodlightCompanyDomainsModel,
        private FloodlightIpDomainLookupsModel $floodlightIpDomainLookupsModel,
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

        $companyDomain = $this->saveDomain($event);
        if ($this->config->isProTier() && null !== $companyDomain) {
            $this->saveIpLookup($event->getRequest()->server->get('REMOTE_ADDR'),$companyDomain->getCompany());
        }
    }

    private function saveDomain(PageHitEvent $event): ?FloodlightCompanyDomains
    {
        $dataDomain     = $this->ipLookupRulesHelper->getDomain($event->getRequest()->server->get('REMOTE_ADDR'));
        $domain         = $dataDomain['domain'];
        $result         = $this->ipLookupRulesHelper->evaluate($event->getHit(), $domain);
        if (
            IpLookupRulesHelper::LEAD_DOMAINS_STATUS_FAILURE === $result['domain']
            || IpLookupRulesHelper::LEAD_DOMAINS_STATUS_BLACKLISTED === $result['domain']
        ) {
            $this->logger->error('Domain is blacklisted or failed to evaluate: '.$domain);
            return null;
        }

        return $this->floodlightCompanyDomainsModel->save($domain, $event->getHit()->getLead(), $dataDomain['details']);
    }

    private function saveIpLookup(string $ip, Company $company): void
    {
        if (
            !$this->ipLookupRulesHelper->isIp($ip)
            || !$this->floodlightIpDomainLookupsModel->getRepository()->isNew($ip)
        ) {
            return;
        }

        $data           = $this->flooglightService->get($ip);
        if (false === $data) {
            $this->logger->error('Floodlight API failed to return data for IP: '.$ip);
            return;
        }

        $floodlightDTO = new FloodlightDTO($data);
        $ipDomainLookup = $floodlightDTO->fillIpLookUp();
        $this->floodlightIpDomainLookupsModel->saveEntity($ipDomainLookup);
        $this->fieldHelper->updateProperties($floodlightDTO);
        $company       = $floodlightDTO->fillCompany($company);
        $this->companyModel->saveEntity($company);

    }
}
