<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryIncentives\permissions\ManageIncentives;
use humhub\modules\thiscoveryIncentives\permissions\ManageSpaceIncentives;
use Yii;

class Access
{
    public static function canManageNetwork(): bool
    {
        if (!Yii::$app->has('user') || Yii::$app->user->isGuest) {
            return false;
        }
        return Yii::$app->user->isAdmin()
            || Yii::$app->user->can(ManageIncentives::class)
            || Yii::$app->user->can(ManageModules::class);
    }

    public static function canManageSpace(Space $space): bool
    {
        if (self::canManageNetwork()) {
            return true;
        }
        return $space->getPermissionManager()->can(ManageSpaceIncentives::class);
    }
}
