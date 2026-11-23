<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\helpers\Links;
use yii\helpers\Html;

/** @var humhub\modules\space\models\Space|null $space */
/** @var string $active */

$items = [
    'index' => 'Rewards',
    'register' => 'Payment register',
];
?>
<ul class="nav nav-tabs" style="margin-bottom: 15px;">
    <?php foreach ($items as $action => $label): ?>
        <li class="nav-item">
            <?= Html::a(Yii::t('ThiscoveryIncentivesModule.base', $label), Links::to($space, $action), [
                'class' => 'nav-link' . ($active === $action ? ' active' : ''),
            ]) ?>
        </li>
    <?php endforeach; ?>
    <?php if (!$space): ?>
        <li class="nav-item">
            <?= Html::a(Yii::t('ThiscoveryIncentivesModule.base', 'Ayda settings'), Links::to(null, 'settings'), [
                'class' => 'nav-link' . ($active === 'settings' ? ' active' : ''),
            ]) ?>
        </li>
    <?php endif; ?>
    <?php $module = Yii::$app->getModule('thiscovery-incentives'); ?>
    <?php if (!$module || !$module->hasCredentials()): ?>
        <li class="nav-item"><span class="nav-link text-warning">API password not set</span></li>
    <?php endif; ?>
</ul>
