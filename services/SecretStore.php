<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\Module;
use Yii;

class SecretStore
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        return base64_encode(Yii::$app->security->encryptByKey($plain, self::key()));
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        $raw = base64_decode($stored, true);
        if ($raw === false) {
            return '';
        }
        $plain = Yii::$app->security->decryptByKey($raw, self::key());
        return $plain === false ? '' : $plain;
    }

    public static function module(): Module
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-incentives');
        return $module;
    }

    private static function key(): string
    {
        $secret = '';
        if (Yii::$app->has('settings')) {
            $secret = (string)Yii::$app->settings->get('secret');
        }
        if ($secret === '') {
            $secret = 'thiscovery-incentives';
        }
        return hash('sha256', $secret . ':thiscovery-incentives', true);
    }
}
