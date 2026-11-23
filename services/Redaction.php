<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use humhub\modules\user\models\User;

class Redaction
{
    public function forgetUser(User $user): void
    {
        $rows = Payment::find()
            ->where(['user_id' => (int)$user->id])
            ->all();
        $byEmail = [];
        if ($user->email) {
            $byEmail = Payment::find()
                ->where(['email' => $user->email, 'identity_removed' => 0])
                ->all();
        }
        $seen = [];
        foreach (array_merge($rows, $byEmail) as $payment) {
            if (isset($seen[(int)$payment->id])) {
                continue;
            }
            $seen[(int)$payment->id] = true;
            $this->redact($payment);
        }
    }

    public function retryPending(): void
    {
        $rows = Payment::find()->where(['forget_pending' => 1])->limit(50)->all();
        foreach ($rows as $payment) {
            $this->forgetAyda($payment);
        }
    }

    private function redact(Payment $payment): void
    {
        $payment->email = null;
        $payment->first_name = null;
        $payment->last_name = null;
        $payment->phone = null;
        $payment->identity_removed = 1;
        $payment->forget_pending = 1;
        Ledger::touch($payment);
        $payment->save(false);
        Ledger::add($payment, 'forgotten', PaymentEvent::SOURCE_SYSTEM, $payment->status, $payment->status, 'Name and email removed. Amounts and status history are kept.');
        $this->forgetAyda($payment);
    }

    private function forgetAyda(Payment $payment): void
    {
        $client = new AydaClient();
        if (!$client->configured()) {
            return;
        }
        $ok = true;
        try {
            if ($payment->ayda_participant_id) {
                $client->forgetParticipant((string)$payment->ayda_participant_id);
            }
            if ($payment->ayda_project_participant_id) {
                $client->forgetProjectParticipant((string)$payment->ayda_project_participant_id);
            }
        } catch (AydaException $e) {
            $ok = false;
            Ledger::add($payment, 'forget_failed', PaymentEvent::SOURCE_AYDA, $payment->status, $payment->status, $e->getMessage());
        }
        if ($ok) {
            $payment->forget_pending = 0;
            Ledger::touch($payment);
            $payment->save(false, ['forget_pending', 'updated_at']);
        }
    }
}
