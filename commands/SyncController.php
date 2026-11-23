<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\commands;

use humhub\modules\thiscoveryIncentives\services\Redaction;
use humhub\modules\thiscoveryIncentives\services\StatusSync;
use yii\console\Controller;

class SyncController extends Controller
{
    public function actionIndex()
    {
        $updated = (new StatusSync())->run();
        (new Redaction())->retryPending();
        $this->stdout("Updated {$updated} incentive status rows.\n");
        return 0;
    }
}
