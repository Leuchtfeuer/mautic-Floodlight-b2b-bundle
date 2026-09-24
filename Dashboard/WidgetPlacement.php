<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\DashboardBundle\Entity\Widget;
use Mautic\DashboardBundle\Entity\WidgetRepository;
use Mautic\UserBundle\Entity\User;

/**
 * Creates the Floodlight usage widget for a supplied user.
 *
 * Placement runs independently of credentials and independently of the current
 * request user (install and update run on the CLI). The operation is idempotent:
 * it checks for an existing Floodlight widget for the user before creating one.
 */
class WidgetPlacement
{
    public const WIDGET_TYPE = 'floodlight.usage';

    private const WIDGET_WIDTH  = 100;

    private const WIDGET_HEIGHT = 330;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Creates the Floodlight widget at the top for the given user if it does not exist yet.
     *
     * @return bool true when a widget was created, false when one already existed
     */
    public function placeForUser(User $user): bool
    {
        $userId = $user->getId();

        if (null === $userId) {
            return false;
        }

        if ($this->userHasWidget($userId)) {
            return false;
        }

        $widget = new Widget();
        $widget->setName('Floodlight for B2B');
        $widget->setType(self::WIDGET_TYPE);
        $widget->setWidth(self::WIDGET_WIDTH);
        $widget->setHeight(self::WIDGET_HEIGHT);
        $widget->setOrdering($this->firstOrdering($userId));
        $widget->setCreatedBy($user);

        $repository = $this->getWidgetRepository();
        $repository->saveEntity($widget);

        return true;
    }

    /**
     * Places the widget for every current Mautic user.
     *
     * @param iterable<User> $users
     */
    public function placeForUsers(iterable $users): void
    {
        foreach ($users as $user) {
            $this->placeForUser($user);
        }
    }

    private function userHasWidget(int $userId): bool
    {
        $count = (int) $this->getWidgetRepository()->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.type = :type')
            ->andWhere('w.createdBy = :userId')
            ->setParameter('type', self::WIDGET_TYPE)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function firstOrdering(int $userId): int
    {
        $min = $this->getWidgetRepository()->createQueryBuilder('w')
            ->select('MIN(w.ordering)')
            ->where('w.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $min ? 0 : ((int) $min) - 1;
    }

    private function getWidgetRepository(): WidgetRepository
    {
        return $this->entityManager->getRepository(Widget::class);
    }
}
