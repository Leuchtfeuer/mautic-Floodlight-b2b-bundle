<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\EventListener;

use Mautic\PluginBundle\Entity\Plugin;
use Mautic\PluginBundle\Event\PluginUpdateEvent;
use Mautic\UserBundle\Entity\UserRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\WidgetPlacement;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\DashboardWidgetLifecycleSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetLifecycleSubscriberTest extends TestCase
{
    #[DataProvider('updateVersionProvider')]
    public function testUpdateBackfillsOnlyWhenOldVersionHasNoWidget(string $oldVersion, bool $expectBackfill): void
    {
        $widgetPlacement = $this->createMock(WidgetPlacement::class);
        $widgetPlacement->expects($expectBackfill ? $this->once() : $this->never())->method('placeForUsers');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findAll')->willReturn([]);

        $plugin = new Plugin();
        $plugin->setName('Floodlight for B2B');

        $subscriber = new DashboardWidgetLifecycleSubscriber($widgetPlacement, $userRepository);
        $subscriber->onPluginUpdate(new PluginUpdateEvent($plugin, $oldVersion, null, null));
    }

    /**
     * @return \Iterator<string, array{string, bool}>
     */
    public static function updateVersionProvider(): \Iterator
    {
        yield 'M5 before feature' => ['5.8.1', true];
        yield 'M5 feature version' => ['5.9.0', false];
        yield 'M5 after feature' => ['5.10.0', false];
        yield 'M7 before feature' => ['7.1.0', true];
        yield 'M7 first version' => ['7.0.0', true];
        yield 'M7 feature version' => ['7.2.0', false];
        yield 'M7 after feature' => ['7.3.0', false];
    }
}
