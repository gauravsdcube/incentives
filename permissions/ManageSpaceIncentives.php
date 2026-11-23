<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;
use Yii;

class ManageSpaceIncentives extends BasePermission
{
    protected $moduleId = 'thiscovery-incentives';

    public $defaultAllowedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_MODERATOR,
    ];

    protected $fixedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_USER,
        Space::USERGROUP_GUEST,
    ];

    public function getTitle()
    {
        return Yii::t('ThiscoveryIncentivesModule.base', 'Manage incentives');
    }

    public function getDescription()
    {
        return Yii::t('ThiscoveryIncentivesModule.base', 'Allows creating rewards for this space, authorising payment, and viewing the payment register for this space.');
    }
}
