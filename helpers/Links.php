<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\helpers;

use humhub\modules\space\models\Space;
use yii\helpers\Url;

class Links
{
    public static function to(?Space $space, string $action, array $params = []): string
    {
        $params[0] = ($space ? '/thiscovery-incentives/reward/' : '/thiscovery-incentives/admin/') . $action;
        if ($space) {
            $params['contentContainer'] = $space;
        }
        return Url::to($params);
    }
}
