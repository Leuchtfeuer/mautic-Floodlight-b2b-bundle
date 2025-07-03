<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class FloodlightSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IpLookupRulesHelper $ipLookupRulesHelper,
        private Config $config,
        private LoggerInterface $logger,
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
        /** @var Request $request */
        assert($request instanceof Request);
        $remoteAddr = $request->server->get('REMOTE_ADDR');

        if (!is_string($remoteAddr)) {
            return;
        }

        $this->ipLookupRulesHelper->setChannel($this->logger);

        if (empty($remoteAddr)) {
            // @TODO no ip for page hit - this should be notified
            $this->ipLookupRulesHelper->notifyNoDataFromGetDomain();
        }

        $this->ipLookupRulesHelper->getAndProcessFloodlightApiData($remoteAddr, $event->getHit()->getLead());
    }
}
