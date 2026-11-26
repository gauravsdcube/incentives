<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use humhub\modules\thiscoveryIncentives\Module;

class m261125_123000_production_api_base extends Migration
{
    private const DEMO_API_BASE = 'https://technology-demo.helloayda.com';

    public function safeUp()
    {
        $this->move(self::DEMO_API_BASE, Module::DEFAULT_API_BASE);
    }

    public function safeDown()
    {
        $this->move(Module::DEFAULT_API_BASE, self::DEMO_API_BASE);
    }

    private function move(string $from, string $to): void
    {
        $module = Yii::$app->getModule('thiscovery-incentives');
        if (!$module instanceof Module) {
            return;
        }
        $current = rtrim(trim((string)$module->settings->get(Module::SETTING_API_BASE, '')), '/');
        if ($current !== '' && strcasecmp($current, $from) !== 0) {
            return;
        }
        $module->settings->set(Module::SETTING_API_BASE, $to);
        $module->settings->set(Module::SETTING_TOKEN, '');
        $module->settings->set(Module::SETTING_TOKEN_EXPIRY, '');
        if (strcasecmp($from, self::DEMO_API_BASE) === 0) {
            $this->clearDemoLinks();
        }
    }

    /**
     * Project and participant ids created on the demo service are not valid in production.
     * Incentive ids on payments already sent are kept so the register still shows them.
     */
    private function clearDemoLinks(): void
    {
        $this->update('{{%incentive_reward}}', ['ayda_project_id' => null], 'ayda_project_id IS NOT NULL');
        $this->update('{{%incentive_payment}}', ['ayda_participant_id' => null], 'ayda_participant_id IS NOT NULL');
        $this->update('{{%incentive_payment}}', ['ayda_project_participant_id' => null], 'ayda_project_participant_id IS NOT NULL');
    }
}
