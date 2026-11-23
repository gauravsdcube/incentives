<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\models;

use humhub\modules\thiscoveryIncentives\Module;
use humhub\modules\thiscoveryIncentives\services\SecretStore;
use Yii;
use yii\base\Model;

class SettingsForm extends Model
{
    public $apiBase = Module::DEFAULT_API_BASE;
    public $username = '';
    public $password = '';
    public $clearPassword = false;

    public function rules()
    {
        return [
            [['apiBase', 'username'], 'trim'],
            [['apiBase'], 'required'],
            [['apiBase'], 'url'],
            [['username'], 'string', 'max' => 255],
            [['password'], 'string', 'max' => 255],
            [['clearPassword'], 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'apiBase' => Yii::t('ThiscoveryIncentivesModule.base', 'Ayda API address'),
            'username' => Yii::t('ThiscoveryIncentivesModule.base', 'API username'),
            'password' => Yii::t('ThiscoveryIncentivesModule.base', 'API password'),
            'clearPassword' => Yii::t('ThiscoveryIncentivesModule.base', 'Remove the stored password'),
        ];
    }

    public function loadFromModule(Module $module): void
    {
        $this->apiBase = $module->getApiBase();
        $this->username = $module->getApiUsername();
        $this->password = '';
    }

    public function saveTo(Module $module): void
    {
        $module->settings->set(Module::SETTING_API_BASE, rtrim((string)$this->apiBase, '/'));
        $module->settings->set(Module::SETTING_API_USERNAME, trim((string)$this->username));
        if ($this->clearPassword) {
            $module->settings->set(Module::SETTING_API_PASSWORD, '');
        } elseif (trim((string)$this->password) !== '') {
            $module->settings->set(Module::SETTING_API_PASSWORD, SecretStore::encrypt(trim((string)$this->password)));
        }
        $module->settings->set(Module::SETTING_TOKEN, '');
        $module->settings->set(Module::SETTING_TOKEN_EXPIRY, '');
    }

    public function hasStoredPassword(Module $module): bool
    {
        return $module->getApiPassword() !== '';
    }
}
