<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\helpers\Links;
use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\Reward;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var Reward $reward */
/** @var yii\data\ActiveDataProvider $provider */
/** @var humhub\modules\space\models\Space|null $space */

$this->title = $reward->title;
$payments = $provider->getModels();
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($reward->title) ?></div>
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'active' => 'index']) ?>
        <p>
            <?= Html::encode($reward->currency . ' ' . $reward->default_amount) ?>
            · <?= $reward->source === Reward::SOURCE_FORM ? ($reward->mode === Reward::MODE_OPT_IN ? 'Opt-in' : 'Everyone who completes') : 'Community' ?>
            · <?= Html::encode($reward->status) ?>
            <?php if ($reward->ayda_project_id): ?> · Ayda project <?= Html::encode($reward->ayda_project_id) ?><?php endif; ?>
        </p>
        <p class="text-muted">Refresh copies people onto this list. Someone who joins later is added only when you refresh again. Rows already here keep their amount and tick.</p>
        <p><?= Html::a('Edit reward', Links::to($space, 'edit', ['id' => $reward->id]), ['class' => 'btn btn-default']) ?></p>

        <?php if ($reward->isOpen()): ?>
            <?= Html::beginForm(Links::to($space, 'refresh', ['id' => $reward->id])) ?>
                <?= Html::submitButton('Refresh list', ['class' => 'btn btn-default']) ?>
            <?= Html::endForm() ?>
        <?php endif; ?>

        <hr>
        <?= Html::beginForm(Links::to($space, 'save-list', ['id' => $reward->id, 'page' => Yii::$app->request->get('page')])) ?>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Pay</th>
                    <th>Person</th>
                    <th>Email</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td>
                            <?php if ($payment->isUnsent()): ?>
                                <input type="hidden" name="ids[]" value="<?= (int)$payment->id ?>">
                                <input type="checkbox" name="include[]" value="<?= (int)$payment->id ?>" <?= (int)$payment->excluded === 0 ? 'checked' : '' ?>>
                            <?php else: ?>
                                <?= (int)$payment->excluded === 1 ? 'No' : 'Yes' ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Html::encode($payment->displayName()) ?>
                            <?php if ($payment->wave_id): ?><div class="text-muted">Wave <?= (int)$payment->wave_id ?></div><?php endif; ?>
                            <?php if ($payment->last_error && $payment->isUnsent()): ?><div class="text-danger"><?= Html::encode($payment->last_error) ?></div><?php endif; ?>
                        </td>
                        <td><?= Html::encode((int)$payment->identity_removed === 1 ? '' : $payment->email) ?></td>
                        <td>
                            <?php if ($payment->isUnsent()): ?>
                                <input class="form-control" name="amounts[<?= (int)$payment->id ?>]" value="<?= Html::encode($payment->amount) ?>">
                            <?php else: ?>
                                <?= Html::encode($payment->amount) ?>
                            <?php endif; ?>
                            <?php if ($payment->amount_overridden_at): ?><div class="text-muted">Changed</div><?php endif; ?>
                        </td>
                        <td><?= Html::encode(Payment::statusLabel((string)$payment->status)) ?></td>
                        <td><?= Html::a('History', Links::to($space, 'payment', ['id' => $payment->id])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= LinkPager::widget(['pagination' => $provider->pagination]) ?>
            <?php if ($reward->isOpen()): ?>
                <?= Html::submitButton('Save this page', ['class' => 'btn btn-default']) ?>
            <?php endif; ?>
        <?= Html::endForm() ?>

        <?php if ($reward->isOpen()): ?>
            <p style="margin-top: 12px;">
                <?= Html::beginForm(Links::to($space, 'confirm', ['id' => $reward->id])) ?>
                    <?= Html::submitButton('Review payment', ['class' => 'btn btn-primary']) ?>
                <?= Html::endForm() ?>
            </p>
            <p class="text-muted">Review uses the saved ticks and amounts, including people on other pages. Save this page first if you have just made changes.</p>
        <?php endif; ?>

        <?= Html::beginForm(Links::to($space, 'close', ['id' => $reward->id])) ?>
            <?= Html::submitButton($reward->isOpen() ? 'Close reward' : 'Reopen reward', ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
