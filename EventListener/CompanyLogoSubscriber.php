<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\LeadBundle\Entity\Company;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanyLogoSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PathsHelper $pathsHelper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['addCompanyLogo', 0],
        ];
    }

    public function addCompanyLogo(CustomContentEvent $event): void
    {
        if (!$event->checkContext('@MauticLead/Company/company.html.twig', 'company.sidebar.top')) {
            return;
        }

        $company = $event->getVars()['company'] ?? null;
        if (!$company instanceof Company) {
            return;
        }

        $logoFileName = $company->getFieldValue('companylogo_filename');
        if (!is_string($logoFileName) || '' === $logoFileName) {
            return;
        }

        $event->addTemplate(
            '@LeuchtfeuerFloodlightB2B/Company/logo.html.twig',
            [
                'logoPath'    => $this->pathsHelper->getSystemPath('media').'/logos/'.basename($logoFileName),
                'companyName' => $company->getName(),
            ]
        );
    }
}
