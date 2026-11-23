<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;

class RewardController extends ContentContainerController
{
    use ManageTrait;

    public $validContentContainerClasses = [Space::class];

    public function behaviors()
    {
        return self::verbConfig(parent::behaviors());
    }

    protected function getAccessRules()
    {
        return [
            ['login'],
        ];
    }

    protected function scopeSpace(): ?Space
    {
        return $this->contentContainer instanceof Space ? $this->contentContainer : null;
    }
}
