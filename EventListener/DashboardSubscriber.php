<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\CoreBundle\Helper\Chart\PieChart;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\DashboardBundle\DashboardEvents;
use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\Event\WidgetTypeListEvent;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageData;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageProvider;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\WidgetPlacement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Registers the fixed Floodlight usage widget type and renders it. It handles
 * only that widget type and never interferes with other dashboard widgets.
 */
class DashboardSubscriber implements EventSubscriberInterface
{
    private const TEMPLATE   = '@LeuchtfeuerFloodlightB2B/Dashboard/usage.html.twig';

    private const BUNDLE     = 'others';

    private const PLAN       = 'Free';

    private const PORTAL_URL = 'https://floodlight-b2b.com/';

    private const SHOP_URL   = 'https://floodlight-b2b.com/';

    public function __construct(
        private UsageProvider $usageProvider,
        private UserHelper $userHelper,
        private CoreParametersHelper $coreParametersHelper,
        private TranslatorInterface $translator,
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

        if (!$event->isCached()) {
            $event->setTemplateData($this->buildTemplateData());
        }

        $event->setTemplate(self::TEMPLATE);
        $event->stopPropagation();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTemplateData(): array
    {
        $base = [
            'plan'      => self::PLAN,
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
            'state'        => 'success',
            'monthlyLimit' => $usage->getMonthlyLimit(),
            'remaining'    => $usage->getRemaining(),
            'chartData'    => $this->buildChartData($usage),
            'buffer'       => $usage->getBuffer(),
            'nextRenewal'  => $usage->getNextRenewal(),
            'daysLeft'     => $this->calculateDaysLeft($usage),
        ]);
    }

    /**
     * @return array{labels: mixed[], datasets: mixed[]}
     */
    private function buildChartData(UsageData $usage): array
    {
        $chart              = new PieChart();
        $remainingForChart  = min($usage->getRemaining(), $usage->getMonthlyLimit());
        $chart->setDataset(
            $this->translator->trans('leuchtfeuerfloodlightb2b.dashboard.usage.chart.remaining'),
            $remainingForChart
        );
        $chart->setDataset(
            $this->translator->trans('leuchtfeuerfloodlightb2b.dashboard.usage.chart.used'),
            $usage->getMonthlyLimit() - $remainingForChart
        );

        return $chart->render();
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

    /**
     * Non-negative whole calendar days until renewal, in the current user's
     * Mautic timezone. A past renewal date returns zero.
     */
    private function calculateDaysLeft(UsageData $usage): int
    {
        $timezone = new \DateTimeZone($this->resolveTimezone());

        $today   = (new \DateTimeImmutable('now', $timezone))->setTime(0, 0);
        $renewal = $usage->getNextRenewal()->setTimezone($timezone)->setTime(0, 0);

        $days = (int) $today->diff($renewal)->format('%r%a');

        return max(0, $days);
    }

    private function resolveTimezone(): string
    {
        $user     = $this->userHelper->getUser();
        $timezone = $user->getTimezone();

        if (empty($timezone)) {
            $timezone = $this->coreParametersHelper->get('default_timezone');
        }

        return empty($timezone) ? date_default_timezone_get() : $timezone;
    }
}
