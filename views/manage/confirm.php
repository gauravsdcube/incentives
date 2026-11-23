<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


use humhub\modules\thiscoveryIncentives\helpers\Links;
use humhub\modules\thiscoveryIncentives\models\Reward;
use humhub\modules\thiscoveryIncentives\services\Money;
use yii\helpers\Html;

/** @var Reward $reward */
/** @var array $quote */
/** @var humhub\modules\space\models\Space|null $space */

$this->title = 'Review payment';
$balanceKnown = $quote['balance'] !== null;
$short = $balanceKnown && Money::toCents($quote['balance']) < Money::toCents($quote['total']);
?>
<div class="panel panel-default">
    <div class="panel-heading">Review payment for <?= Html::encode($reward->title) ?></div>
    <div class="panel-body">
        <p>Authorising this payment takes the incentive, the fee, and VAT from the Ayda wallet and emails each person a link to claim their payment.</p>
        <ul>
            <li><?= (int)$quote['count'] ?> people</li>
            <li>Incentives <?= Html::encode($quote['currency'] . ' ' . $quote['amount']) ?></li>
            <li>Fees <?= Html::encode($quote['currency'] . ' ' . $quote['fee']) ?></li>
            <li>VAT <?= Html::encode($quote['currency'] . ' ' . $quote['tax']) ?></li>
            <li>Total <?= Html::encode($quote['currency'] . ' ' . $quote['total']) ?></li>
            <li>Wallet <?= $balanceKnown ? Html::encode($quote['currency'] . ' ' . number_format((float)$quote['balance'], 2, '.', '')) : 'not available' ?></li>
        </ul>
        <?php if ($short): ?>
            <p class="text-danger">The wallet balance is below the total. Ayda will reject the payment until the wallet is topped up.</p>
        <?php endif; ?>
        <table class="table">
            <thead><tr><th>Person</th><th>Email</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($quote['payments'], 0, 30) as $payment): ?>
                <tr>
                    <td><?= Html::encode($payment->displayName()) ?></td>
                    <td><?= Html::encode($payment->email) ?></td>
                    <td><?= Html::encode($payment->currency . ' ' . $payment->amount) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($quote['payments']) > 30): ?>
            <p class="text-muted">Showing the first 30 of <?= (int)$quote['count'] ?>.</p>
        <?php endif; ?>
        <?= Html::beginForm(Links::to($space, 'authorise', ['id' => $reward->id]), 'post', [
            'onsubmit' => "return confirm('Send these payments to Ayda now?');",
        ]) ?>
            <?= Html::submitButton('Authorise payment', [
                'class' => 'btn btn-primary',
                'disabled' => $short,
            ]) ?>
            <?= Html::a('Back', Links::to($space, 'view', ['id' => $reward->id]), ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
