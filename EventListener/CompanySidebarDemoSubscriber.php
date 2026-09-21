<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Company;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Local demonstration of the company sidebar hook for MTC-11921.
 */
final class CompanySidebarDemoSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectPlaceholder'];
    }

    public function injectPlaceholder(CustomContentEvent $event): void
    {
        if (!$event->checkContext('@MauticLead/Company/company.html.twig', 'company.sidebar.top')) {
            return;
        }

        if (!($event->getVars()['company'] ?? null) instanceof Company) {
            return;
        }

        $event->addTemplate('@LeuchtfeuerFloodlightB2B/Company/sidebar_demo.html.twig');
    }
}
