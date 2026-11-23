<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\helpers\Links;
use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\Reward;
use humhub\modules\thiscoveryIncentives\services\RegisterQuery;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var RegisterQuery $filter */
/** @var yii\data\ActiveDataProvider $provider */
/** @var array $totals */
/** @var humhub\modules\space\models\Space|null $space */
/** @var array $rewards */

$this->title = 'Payment register';
$statuses = [
    '' => 'Any status',
    Payment::STATUS_DRAFT => Payment::statusLabel(Payment::STATUS_DRAFT),
    Payment::STATUS_FAILED => Payment::statusLabel(Payment::STATUS_FAILED),
    Payment::STATUS_AUTHORISED => Payment::statusLabel(Payment::STATUS_AUTHORISED),
    Payment::STATUS_RELEASED => Payment::statusLabel(Payment::STATUS_RELEASED),
    Payment::STATUS_REQUIRES_REVIEW => Payment::statusLabel(Payment::STATUS_REQUIRES_REVIEW),
    Payment::STATUS_PAID => Payment::statusLabel(Payment::STATUS_PAID),
    Payment::STATUS_CANCELLED => Payment::statusLabel(Payment::STATUS_CANCELLED),
];
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'active' => 'register']) ?>
        <p>
            <?= (int)$totals['count'] ?> payments
            · Authorised <?= Html::encode($totals['authorised']) ?>
            · Fees <?= Html::encode($totals['fees']) ?>
            · VAT <?= Html::encode($totals['tax']) ?>
            · Paid <?= Html::encode($totals['paid']) ?>
            · Unclaimed <?= Html::encode($totals['unclaimed']) ?>
            · Cancelled <?= Html::encode($totals['cancelled']) ?>
        </p>
        <?= Html::beginForm(Links::to($space, 'register'), 'get', ['class' => 'form-inline']) ?>
            <?= Html::dropDownList('status', $filter->status, $statuses, ['class' => 'form-control']) ?>
            <?= Html::dropDownList('source', $filter->source, ['' => 'Any type', Reward::SOURCE_FORM => 'Survey', Reward::SOURCE_COMMUNITY => 'Community'], ['class' => 'form-control']) ?>
            <?= Html::dropDownList('rewardId', $filter->rewardId, ['' => 'Any reward'] + $rewards, ['class' => 'form-control']) ?>
            <input class="form-control" type="date" name="from" value="<?= Html::encode($filter->from) ?>">
            <input class="form-control" type="date" name="to" value="<?= Html::encode($filter->to) ?>">
            <input class="form-control" name="q" value="<?= Html::encode($filter->q) ?>" placeholder="Name, email, or reward">
            <?= Html::submitButton('Filter', ['class' => 'btn btn-default']) ?>
        <?= Html::endForm() ?>
        <?= Html::beginForm(Links::to($space, 'export')) ?>
            <input type="hidden" name="status" value="<?= Html::encode($filter->status) ?>">
            <input type="hidden" name="source" value="<?= Html::encode($filter->source) ?>">
            <input type="hidden" name="rewardId" value="<?= Html::encode((string)$filter->rewardId) ?>">
            <input type="hidden" name="from" value="<?= Html::encode($filter->from) ?>">
            <input type="hidden" name="to" value="<?= Html::encode($filter->to) ?>">
            <input type="hidden" name="q" value="<?= Html::encode($filter->q) ?>">
            <?= Html::submitButton('Export CSV', ['class' => 'btn btn-default']) ?>
        <?= Html::endForm() ?>
        <table class="table table-hover">
            <thead>
            <tr>
                <th>When</th>
                <th>Reward</th>
                <th>Person</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($provider->getModels() as $payment): ?>
                <tr>
                    <td><?= Html::encode($payment->created_at) ?></td>
                    <td><?= Html::encode($payment->reward ? $payment->reward->title : '') ?></td>
                    <td><?= Html::a(Html::encode($payment->displayName()), Links::to($space, 'payment', ['id' => $payment->id])) ?></td>
                    <td><?= Html::encode($payment->currency . ' ' . $payment->amount) ?></td>
                    <td><?= Html::encode(Payment::statusLabel((string)$payment->status)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= LinkPager::widget(['pagination' => $provider->pagination]) ?>
    </div>
</div>
