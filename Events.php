<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives;

use humhub\helpers\ControllerHelper;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\space\models\Space;
use humhub\modules\space\widgets\Menu;
use humhub\modules\thiscoveryIncentives\permissions\ManageIncentives;
use humhub\modules\thiscoveryIncentives\services\Access;
use humhub\modules\thiscoveryIncentives\services\Redaction;
use humhub\modules\thiscoveryIncentives\services\StatusSync;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\user\models\User;
use humhub\modules\user\widgets\AccountMenu;
use humhub\widgets\TopMenu;
use Yii;
use yii\base\Event;

class Events
{
    public static function onSpaceMenuInit($event): void
    {
        /** @var Menu $menu */
        $menu = $event->sender;
        $space = $menu->space ?? null;
        if (!$space instanceof Space || !$space->moduleManager->isEnabled('thiscovery-incentives')) {
            return;
        }
        if (!Access::canManageSpace($space)) {
            return;
        }

        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryIncentivesModule.base', 'Incentives'),
            'url' => $space->createUrl('/thiscovery-incentives/reward/index'),
            'icon' => 'gift',
            'isActive' => ControllerHelper::isActivePath('thiscovery-incentives', 'reward'),
            'sortOrder' => 460,
        ]));
    }

    public static function onTopMenuInit($event): void
    {
        if (class_exists(\humhub\modules\thiscoveryNavigation\helpers\Navigation::class)
            && \humhub\modules\thiscoveryNavigation\helpers\Navigation::isActive()) {
            return;
        }
        if (!Access::canManageNetwork()) {
            return;
        }

        /** @var TopMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryIncentivesModule.base', 'Incentives'),
            'id' => 'thiscovery-incentives-global',
            'url' => ['/thiscovery-incentives/admin/index'],
            'icon' => 'gift',
            'isActive' => ControllerHelper::isActivePath('thiscovery-incentives', 'admin'),
            'sortOrder' => 460,
        ]));
    }

    public static function onAdminMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }
        $allowed = Yii::$app->user->isAdmin()
            || Yii::$app->user->can(ManageModules::class)
            || Yii::$app->user->can(ManageIncentives::class);
        if (!$allowed) {
            return;
        }

        /** @var AdminMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryIncentivesModule.base', 'Thiscovery Incentives'),
            'id' => 'thiscovery-incentives-admin',
            'icon' => 'gift',
            'url' => ['/thiscovery-incentives/admin/index'],
            'sortOrder' => 570,
            'isActive' => ControllerHelper::isActivePath('thiscovery-incentives', 'admin'),
            'isVisible' => true,
        ]));
    }

    public static function onAccountMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }
        /** @var AccountMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryIncentivesModule.base', 'My incentives'),
            'id' => 'account-thiscovery-incentives',
            'icon' => 'gift',
            'url' => ['/thiscovery-incentives/account/index'],
            'sortOrder' => 320,
            'isActive' => ControllerHelper::isActivePath('thiscovery-incentives', 'account'),
        ]));
    }

    public static function onUserDelete(Event $event): void
    {
        $user = $event->sender;
        if ($user instanceof User) {
            try {
                (new Redaction())->forgetUser($user);
            } catch (\Throwable $e) {
                Yii::error('Incentive redaction failed: ' . $e->getMessage(), 'thiscovery-incentives');
            }
        }
    }

    public static function onHourlyCron(): void
    {
        try {
            (new StatusSync())->run();
        } catch (\Throwable $e) {
            Yii::error('Incentive status sync failed: ' . $e->getMessage(), 'thiscovery-incentives');
        }
        try {
            (new Redaction())->retryPending();
        } catch (\Throwable $e) {
            Yii::error('Incentive forget retry failed: ' . $e->getMessage(), 'thiscovery-incentives');
        }
    }
}
