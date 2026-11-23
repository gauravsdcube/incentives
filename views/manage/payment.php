<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\helpers\Links;
use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use yii\helpers\Html;

/** @var Payment $payment */
/** @var PaymentEvent[] $events */
/** @var humhub\modules\space\models\Space|null $space */

$this->title = 'Payment ' . (int)$payment->id;
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <p>
            <?= Html::encode($payment->displayName()) ?>
            · <?= Html::encode($payment->currency . ' ' . $payment->amount) ?>
            · <?= Html::encode(Payment::statusLabel((string)$payment->status)) ?>
        </p>
        <p class="text-muted">
            <?php if ((int)$payment->identity_removed !== 1): ?>
                <?= Html::encode((string)$payment->email) ?>
            <?php else: ?>
                Identity removed. Amounts and history are kept.
            <?php endif; ?>
            <?php if ($payment->ayda_incentive_id): ?>
                · Ayda incentive <?= Html::encode($payment->ayda_incentive_id) ?>
            <?php endif; ?>
            <?php if ($payment->fee_amount !== null): ?>
                · Fee <?= Html::encode((string)$payment->fee_amount) ?> · VAT <?= Html::encode((string)$payment->tax_amount) ?>
            <?php endif; ?>
        </p>
        <?php if ($payment->last_error): ?>
            <p class="text-danger"><?= Html::encode($payment->last_error) ?></p>
        <?php endif; ?>

        <h2>History</h2>
        <table class="table">
            <thead><tr><th>When</th><th>What</th><th>From</th><th>To</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?= Html::encode($event->at) ?></td>
                    <td><?= Html::encode($event->action) ?><?php if ($event->actor_user_id): ?> · user <?= (int)$event->actor_user_id ?><?php endif; ?></td>
                    <td><?= Html::encode((string)$event->from_status) ?></td>
                    <td><?= Html::encode((string)$event->to_status) ?></td>
                    <td><?= Html::encode((string)$event->detail) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($payment->status === Payment::STATUS_REQUIRES_REVIEW && $payment->ayda_incentive_id): ?>
            <?= Html::beginForm(Links::to($space, 'approve', ['id' => $payment->id])) ?>
                <label>Reason <input class="form-control" name="reason" maxlength="128"></label>
                <?= Html::submitButton('Approve in Ayda', ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        <?php endif; ?>
        <?php if ($payment->ayda_incentive_id && !in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_CANCELLED], true)): ?>
            <?= Html::beginForm(Links::to($space, 'cancel', ['id' => $payment->id]), 'post', [
                'onsubmit' => "return confirm('Cancel this payment in Ayda?');",
            ]) ?>
                <label>Reason <input class="form-control" name="reason" maxlength="128"></label>
                <?= Html::submitButton('Cancel payment', ['class' => 'btn btn-default']) ?>
            <?= Html::endForm() ?>
        <?php endif; ?>
        <p><?= Html::a('Back to reward', Links::to($space, 'view', ['id' => $payment->reward_id])) ?></p>
    </div>
</div>
