<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class m261119_140000_incentive_tables extends Migration
{
    public function safeUp()
    {
        if (!$this->db->getTableSchema('{{%incentive_reward}}', true)) {
            $this->createTable('{{%incentive_reward}}', [
                'id' => $this->primaryKey(),
                'source' => $this->string(16)->notNull(),
                'title' => $this->string(128)->notNull(),
                'form_id' => $this->integer()->null(),
                'space_id' => $this->integer()->null(),
                'audience' => $this->string(16)->notNull(),
                'group_id' => $this->integer()->null(),
                'currency' => $this->char(3)->notNull()->defaultValue('GBP'),
                'default_amount' => $this->decimal(12, 2)->notNull(),
                'mode' => $this->string(16)->notNull()->defaultValue('manual'),
                'email_field_id' => $this->integer()->null(),
                'first_name_field_id' => $this->integer()->null(),
                'last_name_field_id' => $this->integer()->null(),
                'opt_in_field_id' => $this->integer()->null(),
                'opt_in_value' => $this->string(64)->null(),
                'ayda_project_id' => $this->string(36)->null(),
                'status' => $this->string(16)->notNull()->defaultValue('open'),
                'created_by' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_incentive_reward_form', '{{%incentive_reward}}', 'form_id', true);
            $this->createIndex('idx_incentive_reward_space', '{{%incentive_reward}}', 'space_id');
        }

        if (!$this->db->getTableSchema('{{%incentive_payment}}', true)) {
            $this->createTable('{{%incentive_payment}}', [
                'id' => $this->primaryKey(),
                'reward_id' => $this->integer()->notNull(),
                'dedupe_key' => $this->string(80)->notNull(),
                'user_id' => $this->integer()->null(),
                'form_answer_id' => $this->integer()->null(),
                'wave_id' => $this->integer()->null(),
                'email' => $this->string(320)->null(),
                'first_name' => $this->string(128)->null(),
                'last_name' => $this->string(128)->null(),
                'phone' => $this->string(30)->null(),
                'default_amount' => $this->decimal(12, 2)->notNull(),
                'amount' => $this->decimal(12, 2)->notNull(),
                'currency' => $this->char(3)->notNull(),
                'amount_overridden_by' => $this->integer()->null(),
                'amount_overridden_at' => $this->dateTime()->null(),
                'excluded' => $this->boolean()->notNull()->defaultValue(0),
                'idempotency_id' => $this->string(36)->notNull(),
                'ayda_participant_id' => $this->string(36)->null(),
                'ayda_project_participant_id' => $this->string(36)->null(),
                'ayda_incentive_id' => $this->string(36)->null(),
                'ayda_batch_id' => $this->string(36)->null(),
                'fee_amount' => $this->decimal(12, 2)->null(),
                'tax_amount' => $this->decimal(12, 2)->null(),
                'status' => $this->string(32)->notNull()->defaultValue('draft'),
                'ayda_status' => $this->string(32)->null(),
                'trust_risk' => $this->string(16)->null(),
                'selected_by' => $this->integer()->null(),
                'selected_at' => $this->dateTime()->null(),
                'authorised_by' => $this->integer()->null(),
                'authorised_at' => $this->dateTime()->null(),
                'identity_removed' => $this->boolean()->notNull()->defaultValue(0),
                'forget_pending' => $this->boolean()->notNull()->defaultValue(0),
                'last_error' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_incentive_payment_dedupe', '{{%incentive_payment}}', 'dedupe_key', true);
            $this->createIndex('uidx_incentive_payment_idem', '{{%incentive_payment}}', 'idempotency_id', true);
            $this->createIndex('idx_incentive_payment_reward', '{{%incentive_payment}}', ['reward_id', 'status']);
            $this->createIndex('idx_incentive_payment_user', '{{%incentive_payment}}', 'user_id');
            $this->createIndex('idx_incentive_payment_ayda', '{{%incentive_payment}}', 'ayda_incentive_id');
        }

        if (!$this->db->getTableSchema('{{%incentive_payment_event}}', true)) {
            $this->createTable('{{%incentive_payment_event}}', [
                'id' => $this->primaryKey(),
                'payment_id' => $this->integer()->notNull(),
                'at' => $this->dateTime()->notNull(),
                'actor_user_id' => $this->integer()->null(),
                'source' => $this->string(16)->notNull(),
                'action' => $this->string(32)->notNull(),
                'from_status' => $this->string(32)->null(),
                'to_status' => $this->string(32)->null(),
                'ayda_status' => $this->string(32)->null(),
                'detail' => $this->text()->null(),
            ]);
            $this->createIndex('idx_incentive_event_payment', '{{%incentive_payment_event}}', ['payment_id', 'id']);
        }

        if (!$this->db->getTableSchema('{{%incentive_export_log}}', true)) {
            $this->createTable('{{%incentive_export_log}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->null(),
                'space_id' => $this->integer()->null(),
                'row_count' => $this->integer()->notNull()->defaultValue(0),
                'filters_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_incentive_export_user', '{{%incentive_export_log}}', 'created_at');
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%incentive_export_log}}');
        $this->dropTable('{{%incentive_payment_event}}');
        $this->dropTable('{{%incentive_payment}}');
        $this->dropTable('{{%incentive_reward}}');
    }
}
