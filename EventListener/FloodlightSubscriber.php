<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\ClientIpHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class FloodlightSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IpLookupRulesHelper $ipLookupRulesHelper,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ClientIpHelper $clientIpHelper,
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
        $lead = $event->getHit()->getLead();
        if ($this->isKnowLead($lead)) {
            return;
        }

        $ignoreSingleClickSettings = $this->config->getIgnoreSingleClickSettings();
        $isValid                   = $this->ipLookupRulesHelper->check($event->getHit(), $ignoreSingleClickSettings);

        if (false === $isValid) {
            return;
        }

        $this->saveDomains($event);
    }

    private function saveDomains(PageHitEvent $event): void
    {
        $request = $event->getRequest();
        /** @var Request $request */

        $clientIp = $this->clientIpHelper->getClientIp($request);

        if (null === $clientIp || empty($clientIp)) {
            $this->ipLookupRulesHelper->setChannel($this->logger);
            $this->ipLookupRulesHelper->notifyNoDataFromGetDomain();

            return;
        }

        $this->ipLookupRulesHelper->setChannel($this->logger);
        $this->ipLookupRulesHelper->getAndProcessFloodlightApiData($clientIp, $event->getHit()->getLead());
    }

    private function isKnowLead(Lead $lead): bool
    {
        return $lead->getFirstname()
            || $lead->getLastname()
            || $lead->getEmail();
    }
}
