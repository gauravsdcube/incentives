<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\helpers\Links;
use humhub\modules\thiscoveryIncentives\models\Reward;
use yii\helpers\Html;

/** @var Reward[] $rewards */
/** @var humhub\modules\space\models\Space|null $space */

$this->title = Yii::t('ThiscoveryIncentivesModule.base', 'Incentives');
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'active' => 'index']) ?>
        <p>
            <?= Html::a('New community reward', Links::to($space, 'create', ['source' => Reward::SOURCE_COMMUNITY]), ['class' => 'btn btn-primary', 'data-pjax-prevent' => '']) ?>
            <?= Html::a('New survey reward', Links::to($space, 'create', ['source' => Reward::SOURCE_FORM]), ['class' => 'btn btn-default', 'data-pjax-prevent' => '']) ?>
        </p>
        <p class="text-muted">A manager authorises every payment. Nothing is sent to Ayda until that confirmation.</p>
        <?php if ($rewards === []): ?>
            <p>No rewards yet.</p>
        <?php else: ?>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Reward</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rewards as $reward): ?>
                    <tr>
                        <td><?= Html::a(Html::encode($reward->title), Links::to($space, 'view', ['id' => $reward->id])) ?></td>
                        <td><?= $reward->source === Reward::SOURCE_FORM ? 'Survey' : 'Community' ?></td>
                        <td><?= Html::encode($reward->currency . ' ' . $reward->default_amount) ?></td>
                        <td><?= Html::encode($reward->status) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
