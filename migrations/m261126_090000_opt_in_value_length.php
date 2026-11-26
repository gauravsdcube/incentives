<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class m261126_090000_opt_in_value_length extends Migration
{
    public function safeUp()
    {
        $table = $this->db->getTableSchema('{{%incentive_reward}}', true);
        if ($table === null || !isset($table->columns['opt_in_value'])) {
            return;
        }
        $this->alterColumn('{{%incentive_reward}}', 'opt_in_value', $this->string(2000));
    }

    public function safeDown()
    {
        echo "m261126_090000_opt_in_value_length cannot be reverted.\n";
        return false;
    }
}
