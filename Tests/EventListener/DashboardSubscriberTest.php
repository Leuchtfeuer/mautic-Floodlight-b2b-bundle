<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\EventListener;

use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\Event\WidgetTypeListEvent;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageData;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageException;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\UsageProvider;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\WidgetPlacement;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\DashboardSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DashboardSubscriberTest extends TestCase
{
    private UsageProvider&MockObject $usageProvider;

    private DashboardSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->usageProvider = $this->createMock(UsageProvider::class);
        $this->subscriber    = new DashboardSubscriber($this->usageProvider);
    }

    public function testRegistersWidgetType(): void
    {
        $event = new WidgetTypeListEvent();
        $this->subscriber->onWidgetListGenerate($event);

        $types = $event->getTypes();
        $flat  = array_merge(...array_values($types));

        self::assertArrayHasKey(WidgetPlacement::WIDGET_TYPE, $flat);
    }

    public function testIgnoresUnrelatedWidgetType(): void
    {
        $event = $this->createDetailEvent('some.other.widget');
        $this->usageProvider->expects(self::never())->method('getUsage');
        $event->expects(self::never())->method('setTemplate');
        $event->expects(self::never())->method('stopPropagation');

        $this->subscriber->onWidgetDetailGenerate($event);
    }

    public function testRendersSuccessfulState(): void
    {
        $nextRenewal = new \DateTimeImmutable('2025-08-14 22:00:00', new \DateTimeZone('UTC'));
        $usage       = new UsageData(134, 500, 200, $nextRenewal, 'Professional');
        $this->usageProvider->method('getUsage')->willReturn($usage);

        $event = $this->createDetailEvent(WidgetPlacement::WIDGET_TYPE);
        $event->expects(self::once())->method('setTemplate')->with('@LeuchtfeuerFloodlightB2B/Dashboard/usage.html.twig');
        $event->expects(self::once())->method('stopPropagation');

        $this->subscriber->onWidgetDetailGenerate($event);

        $data = $this->capturedData;
        self::assertSame('success', $data['state']);
        self::assertSame('Professional', $data['plan']);
        self::assertSame(500, $data['monthlyLimit']);
        self::assertSame(200, $data['buffer']);
        self::assertSame(366, $data['usedThisMonth']);
        // Credits remaining include the buffer: 134 + 200.
        self::assertSame(334, $data['creditsRemaining']);
        // Remaining share of monthly credits plus buffer: 334 / 700.
        self::assertEqualsWithDelta(47.714, $data['remainingPercent'], 0.001);
        self::assertSame($nextRenewal, $data['nextRenewal']);
        self::assertSame('https://floodlight-b2b.com/', $data['portalUrl']);
        self::assertSame('https://floodlight-b2b.com/', $data['shopUrl']);
    }

    public function testAlwaysFetchesCurrentUsage(): void
    {
        $usage = new UsageData(134, 500, 200, new \DateTimeImmutable('+1 day'), 'Free');
        $this->usageProvider->expects(self::once())->method('getUsage')->willReturn($usage);

        $event = $this->createDetailEvent(WidgetPlacement::WIDGET_TYPE);
        $event->expects(self::never())->method('isCached');

        $this->subscriber->onWidgetDetailGenerate($event);

        self::assertSame('success', $this->capturedData['state']);
    }

    public function testExhaustedCreditsReturnZeroPercent(): void
    {
        $usage = new UsageData(0, 500, 0, new \DateTimeImmutable('+1 day'), 'Free');
        $this->usageProvider->method('getUsage')->willReturn($usage);

        $event = $this->createDetailEvent(WidgetPlacement::WIDGET_TYPE);
        $this->subscriber->onWidgetDetailGenerate($event);

        self::assertSame(500, $this->capturedData['usedThisMonth']);
        self::assertSame(0, $this->capturedData['creditsRemaining']);
        self::assertSame(0.0, $this->capturedData['remainingPercent']);
    }

    public function testDerivedValuesStayInRangeWhenRemainingExceedsMonthlyLimit(): void
    {
        $usage = new UsageData(600, 500, 200, new \DateTimeImmutable('+1 day'), 'Free');
        $this->usageProvider->method('getUsage')->willReturn($usage);

        $event = $this->createDetailEvent(WidgetPlacement::WIDGET_TYPE);
        $this->subscriber->onWidgetDetailGenerate($event);

        self::assertSame(0, $this->capturedData['usedThisMonth']);
        self::assertSame(800, $this->capturedData['creditsRemaining']);
        self::assertSame(100.0, $this->capturedData['remainingPercent']);
    }

    /**
     * @dataProvider errorStateProvider
     */
    public function testRendersErrorStates(UsageException $exception, string $expectedState): void
    {
        $this->usageProvider->method('getUsage')->willThrowException($exception);

        $event = $this->createDetailEvent(WidgetPlacement::WIDGET_TYPE);
        $this->subscriber->onWidgetDetailGenerate($event);

        $data = $this->capturedData;
        self::assertSame($expectedState, $data['state']);
        // Links remain available in every error state.
        self::assertSame('https://floodlight-b2b.com/', $data['portalUrl']);
        self::assertSame('https://floodlight-b2b.com/', $data['shopUrl']);
        self::assertArrayNotHasKey('plan', $data);
    }

    /**
     * @return array<string, array{UsageException, string}>
     */
    public static function errorStateProvider(): array
    {
        return [
            'setup'               => [UsageException::notConfigured(), 'setup'],
            'invalid credentials' => [UsageException::invalidCredentials(), 'invalid_credentials'],
            'rate limited'        => [UsageException::rateLimited(), 'rate_limited'],
            'unavailable'         => [UsageException::unavailable('boom'), 'error'],
        ];
    }

    /**
     * @var array<string, mixed>
     */
    private array $capturedData = [];

    private function createDetailEvent(string $type): WidgetDetailEvent&MockObject
    {
        $this->capturedData = [];

        $event = $this->createMock(WidgetDetailEvent::class);
        $event->method('getType')->willReturn($type);
        $event->method('isCached')->willReturn(false);
        $event->method('setTemplateData')->willReturnCallback(function (array $data): void {
            $this->capturedData = $data;
        });

        return $event;
    }
}
