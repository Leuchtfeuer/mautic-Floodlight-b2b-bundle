<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\DashboardBundle\DashboardEvents;
use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\Event\WidgetTypeListEvent;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageData;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageProvider;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\WidgetPlacement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the fixed Floodlight usage widget type and renders it. It handles
 * only that widget type and never interferes with other dashboard widgets.
 */
class DashboardSubscriber implements EventSubscriberInterface
{
    private const TEMPLATE   = '@LeuchtfeuerFloodlightB2B/Dashboard/usage.html.twig';

    private const BUNDLE     = 'others';

    private const PORTAL_URL = 'https://floodlight-b2b.com/stripe-customer-portal/?utm_source=app&utm_medium=in-app&utm_campaign=manage_payments&utm_content=widget';

    private const SHOP_URL   = 'https://floodlight-b2b.com/floodlight-for-b2b-pro-plans/?utm_source=app&utm_medium=in-app&utm_campaign=upgrade_plan&utm_content=widget';

    public function __construct(
        private readonly UsageProvider $usageProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DashboardEvents::DASHBOARD_ON_MODULE_LIST_GENERATE   => ['onWidgetListGenerate', 0],
            DashboardEvents::DASHBOARD_ON_MODULE_DETAIL_GENERATE => ['onWidgetDetailGenerate', 0],
        ];
    }

    public function onWidgetListGenerate(WidgetTypeListEvent $event): void
    {
        $event->addType(WidgetPlacement::WIDGET_TYPE, self::BUNDLE);
    }

    public function onWidgetDetailGenerate(WidgetDetailEvent $event): void
    {
        if (WidgetPlacement::WIDGET_TYPE !== $event->getType()) {
            return;
        }

        $event->setTemplateData($this->buildTemplateData(), true);

        $event->setTemplate(self::TEMPLATE);
        $event->stopPropagation();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTemplateData(): array
    {
        $base = [
            'portalUrl' => self::PORTAL_URL,
            'shopUrl'   => self::SHOP_URL,
            'state'     => 'error',
        ];

        try {
            $usage = $this->usageProvider->getUsage();
        } catch (UsageException $e) {
            return array_merge($base, ['state' => $this->stateForCategory($e->getCategory())]);
        }

        return array_merge($base, [
            'state'            => 'success',
            'plan'             => $usage->getPlan(),
            'monthlyLimit'     => $usage->getMonthlyLimit(),
            'buffer'           => $usage->getBuffer(),
            'usedThisMonth'    => max(0, $usage->getMonthlyLimit() - $usage->getRemaining()),
            'creditsRemaining' => $this->calculateCreditsRemaining($usage),
            'remainingPercent' => $this->calculateRemainingPercent($usage),
            'nextRenewal'      => $usage->getNextRenewal(),
        ]);
    }

    /**
     * Remaining monthly credits plus the credit pack balance (buffer).
     */
    private function calculateCreditsRemaining(UsageData $usage): int
    {
        return max(0, $usage->getRemaining() + $usage->getBuffer());
    }

    /**
     * Share of all available credits (monthly credits plus buffer) that is
     * still remaining, capped at 100.
     */
    private function calculateRemainingPercent(UsageData $usage): float
    {
        $totalCredits = $usage->getMonthlyLimit() + $usage->getBuffer();

        if ($totalCredits <= 0) {
            return 0.0;
        }

        return min(100.0, $this->calculateCreditsRemaining($usage) / $totalCredits * 100);
    }

    private function stateForCategory(string $category): string
    {
        return match ($category) {
            UsageException::NOT_CONFIGURED      => 'setup',
            UsageException::INVALID_CREDENTIALS => 'invalid_credentials',
            UsageException::RATE_LIMITED        => 'rate_limited',
            default                             => 'error',
        };
    }
}
