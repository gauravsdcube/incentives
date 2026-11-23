<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\models\Payment;
use yii\helpers\Html;

/** @var Payment[] $payments */

$this->title = 'My incentives';
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <p class="text-muted">When a payment is ready to claim, Ayda emails you a link. This page shows the status only.</p>
        <?php if ($payments === []): ?>
            <p>You have no incentives.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Reward</th><th>Amount</th><th>Status</th><th>Sent</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= Html::encode($payment->reward ? $payment->reward->title : 'Incentive') ?></td>
                        <td><?= Html::encode($payment->currency . ' ' . $payment->amount) ?></td>
                        <td><?= Html::encode(Payment::participantStatusLabel((string)$payment->status)) ?></td>
                        <td><?= Html::encode((string)$payment->authorised_at) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
