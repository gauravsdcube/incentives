<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->safeDropTable('incentive_export_log');
        $this->safeDropTable('incentive_payment_event');
        $this->safeDropTable('incentive_payment');
        $this->safeDropTable('incentive_reward');
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }
}
