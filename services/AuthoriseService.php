<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use humhub\modules\thiscoveryIncentives\models\Reward;

class AuthoriseService
{
    private AydaClient $ayda;

    public function __construct(?AydaClient $ayda = null)
    {
        $this->ayda = $ayda ?: new AydaClient();
    }

    /**
     * @return array{count:int,amount:string,fee:string,tax:string,total:string,balance:?float,currency:string,payments:Payment[]}
     */
    public function quote(Reward $reward): array
    {
        $payments = $this->ready($reward);
        $this->assertPayable($payments);
        $amounts = [];
        $amountCents = 0;
        foreach ($payments as $payment) {
            $cents = Money::toCents($payment->amount);
            $amountCents += $cents;
            $amounts[] = (float)Money::fromCents($cents);
        }
        if (!$reward->ayda_project_id) {
            $reward->ayda_project_id = $this->projectId($reward);
            $reward->updated_at = date('Y-m-d H:i:s');
            $reward->save(false, ['ayda_project_id', 'updated_at']);
        }
        $fees = $this->ayda->fees((string)$reward->ayda_project_id, $amounts);
        $balance = null;
        try {
            $balance = $this->ayda->walletBalance((string)$reward->currency);
        } catch (AydaException $e) {
            $balance = null;
        }
        return [
            'count' => count($payments),
            'amount' => Money::fromCents($amountCents),
            'fee' => number_format($fees['feeAmount'], 2, '.', ''),
            'tax' => number_format($fees['taxAmount'], 2, '.', ''),
            'total' => number_format($fees['totalAmount'], 2, '.', ''),
            'balance' => $balance,
            'currency' => (string)$reward->currency,
            'payments' => $payments,
        ];
    }

    /**
     * @return array{sent:int,failed:int}
     */
    public function authorise(Reward $reward): array
    {
        $quote = $this->quote($reward);
        $this->assertFunded($quote);
        $payments = $quote['payments'];
        $payloads = [];
        foreach ($payments as $payment) {
            $payloads[] = $this->payload($reward, $payment);
        }
        $actor = Ledger::actor();
        $now = date('Y-m-d H:i:s');
        if (count($payments) === 1) {
            try {
                $incentive = $this->ayda->authorise($payloads[0]);
                $this->applyIncentive($payments[0], $incentive, $actor, $now);
                return ['sent' => 1, 'failed' => 0];
            } catch (AydaException $e) {
                if ($e->status === 400 && $this->reconcileOne($reward, $payments[0], $actor)) {
                    return ['sent' => 1, 'failed' => 0];
                }
                $this->markFailed($payments[0], $e->getMessage(), $actor);
                return ['sent' => 0, 'failed' => 1];
            }
        }

        $batch = null;
        try {
            $batch = $this->ayda->authoriseBatch($payloads);
        } catch (AydaException $e) {
            $this->rememberRejection($payments, $e->getMessage());
            throw $e;
        }
        $batchId = (string)($batch['id'] ?? '');
        foreach ($payments as $payment) {
            $from = $payment->status;
            $payment->status = Payment::STATUS_AUTHORISED;
            $payment->ayda_batch_id = $batchId !== '' ? $batchId : null;
            $payment->authorised_by = $actor;
            $payment->authorised_at = $now;
            $payment->last_error = null;
            Ledger::touch($payment);
            $payment->save(false);
            Ledger::add($payment, 'batch_accepted', PaymentEvent::SOURCE_AYDA, $from, $payment->status, $batchId !== '' ? 'Batch ' . $batchId : 'Batch accepted.', null, $actor);
        }
        if ($batchId !== '') {
            (new StatusSync($this->ayda))->reconcileBatch($batchId);
        }
        return ['sent' => count($payments), 'failed' => 0];
    }

    /**
     * @param array<string, mixed> $incentive
     */
    public function applyIncentive(Payment $payment, array $incentive, ?int $actor, ?string $now = null): void
    {
        $now = $now ?: date('Y-m-d H:i:s');
        $from = $payment->status;
        $previousAyda = (string)$payment->ayda_status;
        $previousId = (string)$payment->ayda_incentive_id;
        $aydaStatus = (string)($incentive['status'] ?? '');
        $payment->ayda_incentive_id = (string)($incentive['id'] ?? $payment->ayda_incentive_id);
        $payment->ayda_status = $aydaStatus !== '' ? $aydaStatus : $payment->ayda_status;
        if ($aydaStatus !== '') {
            $payment->status = StatusMap::fromAyda($aydaStatus);
        }
        $participant = $incentive['projectParticipant'] ?? null;
        if (is_array($participant)) {
            if (!empty($participant['id'])) {
                $payment->ayda_project_participant_id = (string)$participant['id'];
            }
            $band = $participant['trustScore']['riskBand'] ?? null;
            if (is_string($band) && $band !== '') {
                $payment->trust_risk = $band;
            }
        }
        $fees = $incentive['fees'] ?? null;
        if (is_array($fees)) {
            if (isset($fees['amount'])) {
                $payment->fee_amount = $fees['amount'];
            }
            if (isset($fees['taxAmount'])) {
                $payment->tax_amount = $fees['taxAmount'];
            }
        }
        if (!$payment->authorised_at) {
            $payment->authorised_by = $actor;
            $payment->authorised_at = $now;
        }
        $payment->last_error = null;
        Ledger::touch($payment);
        $payment->save(false);
        $changed = $from !== $payment->status
            || $previousAyda !== (string)$payment->ayda_status
            || $previousId !== (string)$payment->ayda_incentive_id;
        if ($changed) {
            Ledger::add($payment, 'status_changed', PaymentEvent::SOURCE_AYDA, $from, $payment->status, null, $payment->ayda_status, $actor);
        }
    }

    /**
     * @return Payment[]
     */
    public function ready(Reward $reward): array
    {
        if (!$reward->isOpen()) {
            throw new \RuntimeException('This reward is closed.');
        }
        return Payment::find()
            ->where(['reward_id' => (int)$reward->id, 'excluded' => 0])
            ->andWhere(['status' => [Payment::STATUS_DRAFT, Payment::STATUS_FAILED]])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /**
     * @param Payment[] $payments
     */
    private function assertPayable(array $payments): void
    {
        if ($payments === []) {
            throw new \RuntimeException('Nobody on this list is selected for payment.');
        }
        if (!$this->ayda->configured()) {
            throw new \RuntimeException('Add the Ayda API username and password before authorising payment.');
        }
        $problems = [];
        foreach ($payments as $payment) {
            if ($payment->last_error) {
                $problems[] = $payment->displayName() . ': ' . $payment->last_error;
            } elseif (Money::toCents($payment->amount) < 1) {
                $problems[] = $payment->displayName() . ': the amount must be at least 0.01.';
            }
            if (count($problems) >= 8) {
                break;
            }
        }
        if ($problems !== []) {
            throw new \RuntimeException(implode('; ', $problems));
        }
    }

    /**
     * Payment stays blocked until the Ayda wallet balance is shown and covers the quoted total.
     *
     * @param array{balance:?float,total:string,currency:string} $quote
     */
    private function assertFunded(array $quote): void
    {
        $currency = (string)$quote['currency'];
        if ($quote['balance'] === null) {
            throw new \RuntimeException('The Ayda ' . $currency . ' wallet balance is not shown. Add funds to that wallet in Ayda. Payment cannot be authorised until the balance is shown and covers the total.');
        }
        $balance = number_format((float)$quote['balance'], 2, '.', '');
        if (Money::toCents($balance) < Money::toCents($quote['total'])) {
            throw new \RuntimeException('The Ayda ' . $currency . ' wallet has ' . $balance . ', which is below the total of ' . $quote['total'] . '. Add funds in Ayda before authorising.');
        }
    }

    private function projectId(Reward $reward): string
    {
        $name = (string)$reward->title;
        $currency = (string)$reward->currency;
        try {
            $project = $this->ayda->createProject(
                $name,
                $currency,
                'tc-reward-' . (int)$reward->id,
                $reward->isForm() ? 'Survey incentive' : 'Community incentive'
            );
        } catch (AydaException $e) {
            $project = $this->ayda->findProject($name, $currency);
            if ($project === null) {
                throw $e;
            }
        }
        $id = (string)($project['id'] ?? '');
        if ($id === '') {
            throw new AydaException('Ayda did not return a project id.');
        }
        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Reward $reward, Payment $payment): array
    {
        $body = [
            'projectId' => (string)$reward->ayda_project_id,
            'idempotencyId' => (string)$payment->idempotency_id,
            'incentive' => [
                'currencyCode' => (string)$payment->currency,
                'amount' => (float)Money::fromCents(Money::toCents($payment->amount)),
            ],
        ];
        $participantId = $this->participantId($payment);
        if ($participantId) {
            $body['participantId'] = $participantId;
        } else {
            $person = [
                'email' => (string)$payment->email,
                'firstName' => (string)$payment->first_name,
                'lastName' => (string)$payment->last_name,
            ];
            if (trim((string)$payment->phone) !== '') {
                $person['phoneNumber'] = (string)$payment->phone;
            }
            $body['participant'] = $person;
        }
        return $body;
    }

    /**
     * @param Payment[] $payments
     */
    private function rememberRejection(array $payments, string $message): void
    {
        foreach ($payments as $payment) {
            $email = trim((string)$payment->email);
            if ($email === '') {
                continue;
            }
            $reasons = [];
            if (preg_match('/Researcher\s+(\S+)\s+cannot be a participant/i', $message, $researcher)
                && strcasecmp(rtrim($researcher[1], '.'), $email) === 0) {
                $reasons[] = 'This email belongs to an Ayda researcher and cannot be paid as a participant. Leave them unticked.';
            }
            $pattern = '/Incentive for\s+' . preg_quote($email, '/') . '\s+was too small\.\s*The minimum incentive for your project is\s+([0-9]+(?:\.[0-9]+)?)/i';
            if (preg_match($pattern, $message, $match)) {
                $reasons[] = 'Ayda requires at least ' . number_format((float)$match[1], 2, '.', '') . ' for this project.';
            }
            if ($reasons === []) {
                continue;
            }
            $payment->last_error = mb_substr(implode(' ', $reasons), 0, 1000);
            Ledger::touch($payment);
            $payment->save(false, ['last_error', 'updated_at']);
        }
    }

    /** @var array<string, string|null> */
    private array $participantCache = [];

    private function participantId(Payment $payment): ?string
    {
        if ($payment->ayda_participant_id) {
            return (string)$payment->ayda_participant_id;
        }
        $email = strtolower(trim((string)$payment->email));
        if ($email === '' || (int)$payment->identity_removed === 1) {
            return null;
        }
        if (array_key_exists($email, $this->participantCache)) {
            return $this->participantCache[$email];
        }
        $known = Payment::find()
            ->select('ayda_participant_id')
            ->where(['email' => $payment->email])
            ->andWhere(['not', ['ayda_participant_id' => null]])
            ->scalar();
        if (!$known) {
            $paidBefore = Payment::find()
                ->where(['email' => $payment->email])
                ->andWhere(['not', ['authorised_at' => null]])
                ->andWhere(['<>', 'id', (int)$payment->id])
                ->exists();
            if ($paidBefore) {
                try {
                    foreach ($this->ayda->participantsByEmail((string)$payment->email) as $row) {
                        if (!empty($row['id']) && strcasecmp((string)($row['email'] ?? ''), (string)$payment->email) === 0) {
                            $known = (string)$row['id'];
                            break;
                        }
                    }
                } catch (AydaException $e) {
                    $known = null;
                }
            }
        }
        $known = $known ? (string)$known : null;
        $this->participantCache[$email] = $known;
        if ($known) {
            $payment->ayda_participant_id = $known;
            $payment->save(false, ['ayda_participant_id']);
        }
        return $known;
    }

    private function reconcileOne(Reward $reward, Payment $payment, ?int $actor): bool
    {
        if (!$reward->ayda_project_id || !$payment->email) {
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
            $incentives = $this->ayda->projectParticipantIncentives((string)$person['id']);
            foreach ($incentives as $incentive) {
                if (Money::toCents($incentive['amount'] ?? 0) !== Money::toCents($payment->amount)) {
                    continue;
                }
                if (Payment::find()->where(['ayda_incentive_id' => (string)$incentive['id']])->exists()) {
                    continue;
                }
                $payment->ayda_project_participant_id = (string)$person['id'];
                $this->applyIncentive($payment, $incentive, $actor);
                return true;
            }
        }
        return false;
    }

    private function markFailed(Payment $payment, string $message, ?int $actor): void
    {
        $from = $payment->status;
        $payment->status = Payment::STATUS_FAILED;
        $payment->last_error = mb_substr($message, 0, 500);
        Ledger::touch($payment);
        $payment->save(false);
        Ledger::add($payment, 'failed', PaymentEvent::SOURCE_AYDA, $from, Payment::STATUS_FAILED, $payment->last_error, null, $actor);
    }
}
