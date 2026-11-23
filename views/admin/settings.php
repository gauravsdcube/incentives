<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\models\SettingsForm;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var SettingsForm $model */
/** @var bool $stored */
/** @var humhub\modules\space\models\Space|null $space */

$this->title = 'Ayda settings';
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <?= $this->render('@thiscovery-incentives/views/manage/_nav', ['space' => $space, 'active' => 'settings']) ?>
        <p class="text-muted">The password is stored encrypted. Leave it blank to keep the current password. Payment cannot be authorised until a username and password are saved.</p>
        <?php $form = ActiveForm::begin(); ?>
            <?= $form->field($model, 'apiBase') ?>
            <?= $form->field($model, 'username') ?>
            <?= $form->field($model, 'password')->passwordInput(['value' => '', 'autocomplete' => 'new-password'])->hint($stored ? 'A password is already stored.' : 'No password stored yet.') ?>
            <?= $form->field($model, 'clearPassword')->checkbox() ?>
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
        <?php ActiveForm::end(); ?>
        <?= Html::beginForm(['test']) ?>
            <?= Html::submitButton('Test connection', ['class' => 'btn btn-default']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
