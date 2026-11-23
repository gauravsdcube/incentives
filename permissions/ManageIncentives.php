<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\permissions;

use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

class ManageIncentives extends BaseAdminPermission
{
    protected $id = 'manage_incentives';
    protected $moduleId = 'thiscovery-incentives';

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->title = Yii::t('ThiscoveryIncentivesModule.base', 'Manage incentives');
        $this->description = Yii::t('ThiscoveryIncentivesModule.base', 'Allows creating rewards, authorising payment, and viewing the payment register for the whole network.');
    }
}
