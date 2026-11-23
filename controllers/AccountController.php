<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\controllers;

use humhub\components\Controller;
use humhub\modules\thiscoveryIncentives\models\Payment;
use Yii;

class AccountController extends Controller
{
    public $subLayout = '@humhub/modules/user/views/account/_layout';

    protected function getAccessRules()
    {
        return [
            ['login'],
        ];
    }

    public function actionIndex()
    {
        $payments = Payment::find()
            ->where(['user_id' => (int)Yii::$app->user->id, 'identity_removed' => 0])
            ->with('reward')
            ->orderBy(['id' => SORT_DESC])
            ->all();
        return $this->render('index', ['payments' => $payments]);
    }
}
