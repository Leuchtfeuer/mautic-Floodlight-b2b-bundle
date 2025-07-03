<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FloodlightLeadNameHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomTemplateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FloodlightLeadNameHelper $leadNameHelper
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['addLeadNameContent', 0],
        ];
    }

    public function addLeadNameContent(CustomContentEvent $event): void
    {
        if (in_array($event->getContext(), ['lead.grid', 'lead.list.name', 'lead.company.list.name', 'lead.form.name', 'lead.detail.name'])) {
            $vars = $event->getVars();

            $lead = $vars['item'] ?? $vars['contact'] ?? $vars['lead'] ?? null;

            if ($lead instanceof Lead && !$lead->getName()) {
                $floodlightName = $this->leadNameHelper->getFloodlightLeadName($lead);
                if ($floodlightName) {
                    $event->addContent($floodlightName);
                }
            }
        }
    }
}
