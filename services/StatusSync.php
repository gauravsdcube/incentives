<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use humhub\modules\thiscoveryIncentives\models\Reward;

class StatusSync
{
    private AydaClient $ayda;
    private AuthoriseService $authorise;

    public function __construct(?AydaClient $ayda = null)
    {
        $this->ayda = $ayda ?: new AydaClient();
        $this->authorise = new AuthoriseService($this->ayda);
    }

    public function run(int $limit = 100): int
    {
        if (!$this->ayda->configured()) {
            return 0;
        }
        $updated = 0;
        $batches = Payment::find()
            ->select('ayda_batch_id')
            ->where(['not', ['ayda_batch_id' => null]])
            ->andWhere(['ayda_incentive_id' => null])
            ->andWhere(['status' => [Payment::STATUS_AUTHORISED, Payment::STATUS_FAILED]])
            ->groupBy('ayda_batch_id')
            ->limit(20)
            ->column();
        foreach ($batches as $batchId) {
            $updated += $this->reconcileBatch((string)$batchId);
        }

        $rows = Payment::find()
            ->where(['not', ['ayda_incentive_id' => null]])
            ->andWhere(['status' => [Payment::STATUS_AUTHORISED, Payment::STATUS_RELEASED, Payment::STATUS_REQUIRES_REVIEW]])
            ->orderBy(['updated_at' => SORT_ASC])
            ->limit($limit)
            ->all();
        foreach ($rows as $payment) {
            try {
                $incentive = $this->ayda->incentive((string)$payment->ayda_incentive_id);
            } catch (AydaException $e) {
                continue;
            }
            $before = $payment->status;
            $this->authorise->applyIncentive($payment, $incentive, null);
            if ($payment->status !== $before) {
                $updated++;
            }
        }
        return $updated;
    }

    public function reconcileBatch(string $batchId): int
    {
        $payments = Payment::find()->where(['ayda_batch_id' => $batchId, 'ayda_incentive_id' => null])->all();
        if ($payments === []) {
            return 0;
        }
        $reward = Reward::findOne((int)$payments[0]->reward_id);
        if (!$reward || !$reward->ayda_project_id) {
            return 0;
        }
        try {
            $batch = $this->ayda->batch($batchId);
        } catch (AydaException $e) {
            return 0;
        }
        $status = (string)($batch['status'] ?? '');
        if (!in_array($status, ['Completed', 'PartialFailure', 'Failed'], true)) {
            return 0;
        }
        $matched = 0;
        foreach ($payments as $payment) {
            if ($this->matchPayment($reward, $payment)) {
                $matched++;
                continue;
            }
            if (in_array($status, ['PartialFailure', 'Failed'], true)) {
                $from = $payment->status;
                $payment->status = Payment::STATUS_FAILED;
                $payment->last_error = 'Ayda did not create this incentive. The same payment can be sent again.';
                Ledger::touch($payment);
                $payment->save(false);
                Ledger::add($payment, 'failed', PaymentEvent::SOURCE_AYDA, $from, Payment::STATUS_FAILED, $payment->last_error, $status);
            }
        }
        return $matched;
    }

    private function matchPayment(Reward $reward, Payment $payment): bool
    {
        if (!$payment->email || (int)$payment->identity_removed === 1) {
            return false;
        }
        try {
            $people = $this->ayda->projectParticipants((string)$reward->ayda_project_id, (string)$payment->email);
        } catch (AydaException $e) {
            return false;
        }
        foreach ($people as $person) {
            if (empty($person['id'])) {
                continue;
            }
            try {
                $incentives = $this->ayda->projectParticipantIncentives((string)$person['id']);
            } catch (AydaException $e) {
                continue;
            }
            foreach ($incentives as $incentive) {
                if (empty($incentive['id'])) {
                    continue;
                }
                if (Money::toCents($incentive['amount'] ?? 0) !== Money::toCents($payment->amount)) {
                    continue;
                }
                if (Payment::find()->where(['ayda_incentive_id' => (string)$incentive['id']])->andWhere(['<>', 'id', (int)$payment->id])->exists()) {
                    continue;
                }
                $payment->ayda_project_participant_id = (string)$person['id'];
                $this->authorise->applyIncentive($payment, $incentive, null);
                return true;
            }
        }
        return false;
    }
}
