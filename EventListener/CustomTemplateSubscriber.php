<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FloodlightLeadNameHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomTemplateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FloodlightLeadNameHelper $leadNameHelper,
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
        if ('lead.name.after' == $event->getContext()) {
            $vars = $event->getVars();

            $lead = $vars['item'] ?? $vars['contact'] ?? $vars['lead'] ?? null;

            if ($lead instanceof Lead && !$lead->getName()) {
                $floodlightName = $this->leadNameHelper->getFloodlightLeadName($lead);
                if ($floodlightName) {
                    $event->addContent('<div style="clear: left;">'.$floodlightName.'</div>');
                }
            }
        }
    }
}
