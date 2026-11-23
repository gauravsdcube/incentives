<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives;

use humhub\components\console\Application as ConsoleApplication;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryIncentives\permissions\ManageIncentives;
use humhub\modules\thiscoveryIncentives\permissions\ManageSpaceIncentives;
use humhub\modules\thiscoveryIncentives\services\SecretStore;
use Yii;
use yii\helpers\Url;

class Module extends ContentContainerModule
{
    public const SETTING_API_BASE = 'api_base';
    public const SETTING_API_USERNAME = 'api_username';
    public const SETTING_API_PASSWORD = 'api_password';
    public const SETTING_TOKEN = 'api_token';
    public const SETTING_TOKEN_EXPIRY = 'api_token_expiry';

    public const DEFAULT_API_BASE = 'https://technology-demo.helloayda.com';

    public $resourcesPath = 'resources';
    public $icon = 'fa-gift';

    public function init()
    {
        parent::init();
        if (Yii::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'humhub\modules\thiscoveryIncentives\commands';
        }
    }

    public function getName()
    {
        return Yii::t('ThiscoveryIncentivesModule.base', 'Thiscovery Incentives');
    }

    public function getDescription()
    {
        return Yii::t('ThiscoveryIncentivesModule.base', 'Pay survey participants and community members through Ayda. A manager authorises every payment.');
    }

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getPermissions($contentContainer = null)
    {
        if ($contentContainer instanceof Space) {
            return [new ManageSpaceIncentives()];
        }
        if ($contentContainer === null) {
            return [new ManageIncentives()];
        }
        return [];
    }

    public function getContentContainerName(ContentContainerActiveRecord $container)
    {
        return Yii::t('ThiscoveryIncentivesModule.base', 'Incentives');
    }

    public function getContentContainerDescription(ContentContainerActiveRecord $container)
    {
        return Yii::t('ThiscoveryIncentivesModule.base', 'Choose space members for an incentive and authorise payment.');
    }

    public function getConfigUrl()
    {
        return Url::to(['/thiscovery-incentives/admin/settings']);
    }

    public function getApiBase(): string
    {
        $base = trim((string)$this->settings->get(self::SETTING_API_BASE, self::DEFAULT_API_BASE));
        return rtrim($base !== '' ? $base : self::DEFAULT_API_BASE, '/');
    }

    public function hasCredentials(): bool
    {
        return $this->getApiUsername() !== '' && $this->getApiPassword() !== '';
    }

    public function getApiUsername(): string
    {
        return trim((string)$this->settings->get(self::SETTING_API_USERNAME, ''));
    }

    public function getApiPassword(): string
    {
        return SecretStore::decrypt((string)$this->settings->get(self::SETTING_API_PASSWORD, ''));
    }
}
