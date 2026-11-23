<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryIncentives\models\SettingsForm;
use humhub\modules\thiscoveryIncentives\Module;
use humhub\modules\thiscoveryIncentives\services\AydaClient;
use humhub\modules\thiscoveryIncentives\services\AydaException;
use Yii;

class AdminController extends Controller
{
    use ManageTrait;

    public $adminOnly = false;

    public function behaviors()
    {
        return self::verbConfig(parent::behaviors());
    }

    protected function getAccessRules()
    {
        return [
            ['login'],
        ];
    }

    protected function scopeSpace(): ?Space
    {
        return null;
    }

    public function actionSettings()
    {
        $this->guard();
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-incentives');
        $model = new SettingsForm();
        $model->loadFromModule($module);
        $stored = $model->hasStoredPassword($module);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->saveTo($module);
            $this->view->success('Ayda settings saved.');
            return $this->redirect(['settings']);
        }
        return $this->render('settings', [
            'model' => $model,
            'stored' => $stored,
            'space' => null,
        ]);
    }

    public function actionTest()
    {
        $this->guard();
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['settings']);
        }
        try {
            $client = new AydaClient();
            $token = $client->token(true);
            $wallets = $client->wallets();
            $lines = ['Connected. Token expires ' . ($token['expiry'] !== '' ? $token['expiry'] : 'as returned by Ayda') . '.'];
            if ($wallets === []) {
                $lines[] = 'No wallets were returned.';
            }
            foreach ($wallets as $wallet) {
                $lines[] = (string)($wallet['currencyCode'] ?? 'Wallet') . ' balance ' . (string)($wallet['balance'] ?? '');
            }
            $this->view->success(implode(' ', $lines));
        } catch (AydaException $e) {
            $this->view->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->view->error('The connection test failed.');
            Yii::error($e->getMessage(), 'thiscovery-incentives');
        }
        return $this->redirect(['settings']);
    }
}
