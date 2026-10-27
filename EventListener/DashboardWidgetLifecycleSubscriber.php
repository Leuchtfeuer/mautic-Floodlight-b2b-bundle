<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\Event\PluginUpdateEvent;
use Mautic\PluginBundle\PluginEvents;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Event\UserEvent;
use Mautic\UserBundle\UserEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard\WidgetPlacement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Places the Floodlight dashboard widget across the plugin lifecycle:
 * fresh installation, the update that introduces the feature, and future
 * user creation. Placement is idempotent and independent of credentials.
 */
class DashboardWidgetLifecycleSubscriber implements EventSubscriberInterface
{
    private const PLUGIN_NAME = 'Floodlight for B2B';

    /**
     * Plugin versions that introduce the widget on the M5 and M7 lines. The update
     * backfill only runs when the old version has not yet placed the widget, so
     * widgets removed by users are not added again.
     */
    public const M5_FEATURE_VERSION = '5.9.0';

    public const M7_FIRST_VERSION = '7.0.0';

    public const M7_FEATURE_VERSION = '7.2.0';

    public function __construct(
        private readonly WidgetPlacement $widgetPlacement,
        private readonly \Mautic\UserBundle\Entity\UserRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0],
            PluginEvents::ON_PLUGIN_UPDATE  => ['onPluginUpdate', 0],
            UserEvents::USER_POST_SAVE      => ['onUserPostSave', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext(self::PLUGIN_NAME)) {
            return;
        }

        $this->placeForAllUsers();
    }

    public function onPluginUpdate(PluginUpdateEvent $event): void
    {
        if (!$event->checkContext(self::PLUGIN_NAME)) {
            return;
        }

        if (!$this->needsBackfill($event->getOldVersion())) {
            return;
        }

        $this->placeForAllUsers();
    }

    public function onUserPostSave(UserEvent $event): void
    {
        if (!$event->isNew()) {
            return;
        }

        $this->widgetPlacement->placeForUser($event->getUser());
    }

    private function needsBackfill(string $oldVersion): bool
    {
        if (version_compare($oldVersion, self::M5_FEATURE_VERSION, '<')) {
            return true;
        }

        return version_compare($oldVersion, self::M7_FIRST_VERSION, '>=')
            && version_compare($oldVersion, self::M7_FEATURE_VERSION, '<');
    }

    private function placeForAllUsers(): void
    {
        /** @var User[] $users */
        $users = $this->userRepository->findAll();

        $this->widgetPlacement->placeForUsers($users);
    }
}
