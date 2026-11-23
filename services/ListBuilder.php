<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use humhub\modules\thiscoveryIncentives\models\Reward;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;

/**
 * Copies eligible people onto a reward. A later refresh adds people who were
 * missing and updates identity on rows that have not been sent.
 */
class ListBuilder
{
    public const BATCH = 500;

    /**
     * @return array{added:int,refreshed:int,more:bool}
     */
    public function sync(Reward $reward): array
    {
        if (!$reward->isOpen()) {
            throw new \RuntimeException('This reward is closed.');
        }
        if ($reward->isForm()) {
            return $this->syncForm($reward);
        }
        return $this->syncCommunity($reward);
    }

    /**
     * @return array{added:int,refreshed:int,more:bool}
     */
    private function syncForm(Reward $reward): array
    {
        if (!Yii::$app->hasModule('thiscovery-forms') || !class_exists(\humhub\modules\thiscoveryForms\models\FormAnswer::class)) {
            throw new \RuntimeException('Thiscovery Forms is not available.');
        }
        $answerClass = \humhub\modules\thiscoveryForms\models\FormAnswer::class;
        $fieldClass = \humhub\modules\thiscoveryForms\models\FormAnswerField::class;

        $existing = $this->existingKeys((int)$reward->id);
        $query = $answerClass::findParticipants()
            ->andWhere(['form_id' => (int)$reward->form_id])
            ->andWhere(['status' => $answerClass::STATUS_COMPLETE])
            ->andWhere(['or', ['outcome' => ''], ['outcome' => $answerClass::OUTCOME_COMPLETE]])
            ->orderBy(['id' => SORT_ASC]);

        $added = 0;
        $more = false;
        foreach ($query->each(100) as $answer) {
            $key = 'r' . (int)$reward->id . ':a' . (int)$answer->id;
            if (isset($existing[$key])) {
                continue;
            }
            if ($reward->mode === Reward::MODE_OPT_IN && !$this->optedIn($reward, (int)$answer->id, $fieldClass)) {
                continue;
            }
            if ($added >= self::BATCH) {
                $more = true;
                break;
            }
            $this->insertAnswer($reward, $answer, $key, $fieldClass);
            $existing[$key] = true;
            $added++;
        }
        $refreshed = $this->refreshUnsentFormRows($reward, $fieldClass);
        return ['added' => $added, 'refreshed' => $refreshed, 'more' => $more];
    }

    /**
     * @return array{added:int,refreshed:int,more:bool}
     */
    private function syncCommunity(Reward $reward): array
    {
        $existing = $this->existingKeys((int)$reward->id);
        $added = 0;
        $more = false;
        foreach ($this->communityUsers($reward) as $user) {
            if ((int)$user->status === User::STATUS_SOFT_DELETED) {
                continue;
            }
            $key = 'r' . (int)$reward->id . ':u' . (int)$user->id;
            if (isset($existing[$key])) {
                continue;
            }
            if ($added >= self::BATCH) {
                $more = true;
                break;
            }
            $this->insertUser($reward, $user, $key);
            $existing[$key] = true;
            $added++;
        }
        $refreshed = $this->refreshUnsentUsers($reward);
        return ['added' => $added, 'refreshed' => $refreshed, 'more' => $more];
    }

    /**
     * @return \Generator|User[]
     */
    private function communityUsers(Reward $reward)
    {
        if ($reward->audience === Reward::AUDIENCE_SPACE && $reward->space_id) {
            $space = Space::findOne((int)$reward->space_id);
            if ($space) {
                foreach ($space->getMembershipUser(Membership::STATUS_MEMBER)->each(100) as $user) {
                    yield $user;
                }
            }
            return;
        }
        if ($reward->audience === Reward::AUDIENCE_GROUP && $reward->group_id) {
            $group = Group::findOne((int)$reward->group_id);
            if ($group) {
                foreach ($group->getUsers()->each(100) as $user) {
                    yield $user;
                }
            }
            return;
        }
        foreach (User::find()->andWhere(['<>', 'user.status', User::STATUS_SOFT_DELETED])->each(100) as $user) {
            yield $user;
        }
    }

    /**
     * @return array<string, bool>
     */
    private function existingKeys(int $rewardId): array
    {
        $keys = Payment::find()->select('dedupe_key')->where(['reward_id' => $rewardId])->column();
        return array_fill_keys($keys, true);
    }

    private function insertAnswer(Reward $reward, $answer, string $key, string $fieldClass): void
    {
        $identity = $this->answerIdentity($reward, (int)$answer->id, $fieldClass);
        $payment = $this->newPayment($reward, $key);
        $payment->user_id = $answer->created_by ? (int)$answer->created_by : null;
        $payment->form_answer_id = (int)$answer->id;
        $payment->wave_id = $answer->wave_id ? (int)$answer->wave_id : null;
        $this->applyIdentity($payment, $identity['email'], $identity['first'], $identity['last'], null);
        $payment->save(false);
        Ledger::add($payment, 'created', PaymentEvent::SOURCE_SYSTEM, null, Payment::STATUS_DRAFT, 'Added from survey response ' . (int)$answer->id);
    }

    private function insertUser(Reward $reward, User $user, string $key): void
    {
        $payment = $this->newPayment($reward, $key);
        $payment->user_id = (int)$user->id;
        $profile = $user->profile;
        $phone = '';
        if ($profile) {
            $phone = trim((string)($profile->mobile ?: $profile->phone_private ?: $profile->phone_work));
        }
        $this->applyIdentity(
            $payment,
            (string)$user->email,
            $profile ? (string)$profile->firstname : '',
            $profile ? (string)$profile->lastname : '',
            $phone !== '' ? mb_substr($phone, 0, 30) : null
        );
        $payment->save(false);
        Ledger::add($payment, 'created', PaymentEvent::SOURCE_SYSTEM, null, Payment::STATUS_DRAFT, 'Added from community membership.');
    }

    private function newPayment(Reward $reward, string $key): Payment
    {
        $now = date('Y-m-d H:i:s');
        $payment = new Payment();
        $payment->reward_id = (int)$reward->id;
        $payment->dedupe_key = $key;
        $payment->default_amount = $reward->default_amount;
        $payment->amount = $reward->default_amount;
        $payment->currency = $reward->currency;
        $payment->excluded = 0;
        $payment->idempotency_id = Ids::uuid();
        $payment->status = Payment::STATUS_DRAFT;
        $payment->selected_by = Ledger::actor();
        $payment->selected_at = $now;
        $payment->identity_removed = 0;
        $payment->forget_pending = 0;
        $payment->created_at = $now;
        $payment->updated_at = $now;
        return $payment;
    }

    /**
     * @return array{email:string,first:string,last:string}
     */
    private function answerIdentity(Reward $reward, int $answerId, string $fieldClass): array
    {
        $ids = array_filter([
            (int)$reward->email_field_id,
            (int)$reward->first_name_field_id,
            (int)$reward->last_name_field_id,
        ]);
        $values = [];
        if ($ids !== []) {
            $rows = $fieldClass::find()
                ->andWhere(['answer_id' => $answerId, 'field_id' => $ids])
                ->all();
            foreach ($rows as $row) {
                $values[(int)$row->field_id] = self::plain((string)$row->value);
            }
        }
        return [
            'email' => $values[(int)$reward->email_field_id] ?? '',
            'first' => $values[(int)$reward->first_name_field_id] ?? '',
            'last' => $values[(int)$reward->last_name_field_id] ?? '',
        ];
    }

    private function optedIn(Reward $reward, int $answerId, string $fieldClass): bool
    {
        if (!(int)$reward->opt_in_field_id) {
            return false;
        }
        $row = $fieldClass::find()
            ->andWhere(['answer_id' => $answerId, 'field_id' => (int)$reward->opt_in_field_id])
            ->one();
        if (!$row) {
            return false;
        }
        return self::isOptIn((string)$row->value, (string)$reward->opt_in_value);
    }

    public static function isOptIn(string $stored, string $expected): bool
    {
        $candidates = [];
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_scalar($item)) {
                    $candidates[] = (string)$item;
                }
            }
        } else {
            $candidates[] = self::plain($stored);
        }
        $expected = trim($expected);
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            if ($expected !== '' && mb_strtolower($candidate) === mb_strtolower($expected)) {
                return true;
            }
            if (in_array(mb_strtolower($candidate), ['1', 'yes', 'y', 'true', 'on', 'opt in', 'opt-in', 'agree', 'i agree'], true)) {
                return true;
            }
        }
        return false;
    }

    public static function plain(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $value = trim($decoded);
        }
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function applyIdentity(Payment $payment, string $email, string $first, string $last, ?string $phone): void
    {
        $payment->email = mb_substr(trim($email), 0, 320);
        $payment->first_name = mb_substr(trim($first), 0, 128);
        $payment->last_name = mb_substr(trim($last), 0, 128);
        $payment->phone = $phone;
        $payment->last_error = $this->identityError($payment);
    }

    private function identityError(Payment $payment): ?string
    {
        if (!filter_var((string)$payment->email, FILTER_VALIDATE_EMAIL)) {
            return 'A valid email address is required before this person can be paid.';
        }
        if (!NameCheck::valid((string)$payment->first_name)) {
            return 'First name: ' . NameCheck::problem((string)$payment->first_name);
        }
        if (!NameCheck::valid((string)$payment->last_name)) {
            return 'Last name: ' . NameCheck::problem((string)$payment->last_name);
        }
        return null;
    }

    private function refreshUnsentFormRows(Reward $reward, string $fieldClass): int
    {
        $count = 0;
        $rows = Payment::find()
            ->where(['reward_id' => (int)$reward->id])
            ->andWhere(['status' => [Payment::STATUS_DRAFT, Payment::STATUS_FAILED]])
            ->andWhere(['not', ['form_answer_id' => null]])
            ->all();
        foreach ($rows as $payment) {
            $identity = $this->answerIdentity($reward, (int)$payment->form_answer_id, $fieldClass);
            if ($this->writeIdentityIfChanged($payment, $identity['email'], $identity['first'], $identity['last'], $payment->phone)) {
                $count++;
            }
        }
        return $count;
    }

    private function refreshUnsentUsers(Reward $reward): int
    {
        $count = 0;
        $rows = Payment::find()
            ->where(['reward_id' => (int)$reward->id])
            ->andWhere(['status' => [Payment::STATUS_DRAFT, Payment::STATUS_FAILED]])
            ->andWhere(['not', ['user_id' => null]])
            ->with('user')
            ->all();
        foreach ($rows as $payment) {
            $user = $payment->user;
            if (!$user) {
                continue;
            }
            $profile = $user->profile;
            $phone = '';
            if ($profile) {
                $phone = trim((string)($profile->mobile ?: $profile->phone_private ?: $profile->phone_work));
            }
            if ($this->writeIdentityIfChanged(
                $payment,
                (string)$user->email,
                $profile ? (string)$profile->firstname : '',
                $profile ? (string)$profile->lastname : '',
                $phone !== '' ? mb_substr($phone, 0, 30) : null
            )) {
                $count++;
            }
        }
        return $count;
    }

    private function writeIdentityIfChanged(Payment $payment, string $email, string $first, string $last, ?string $phone): bool
    {
        $email = mb_substr(trim($email), 0, 320);
        $first = mb_substr(trim($first), 0, 128);
        $last = mb_substr(trim($last), 0, 128);
        if ((string)$payment->email === $email && (string)$payment->first_name === $first && (string)$payment->last_name === $last && (string)$payment->phone === (string)$phone) {
            $error = $this->identityError($payment);
            if ((string)$payment->last_error !== (string)$error && $payment->isUnsent()) {
                $payment->last_error = $error;
                Ledger::touch($payment);
                $payment->save(false, ['last_error', 'updated_at']);
            }
            return false;
        }
        $payment->email = $email;
        $payment->first_name = $first;
        $payment->last_name = $last;
        $payment->phone = $phone;
        $payment->last_error = $this->identityError($payment);
        Ledger::touch($payment);
        $payment->save(false, ['email', 'first_name', 'last_name', 'phone', 'last_error', 'updated_at']);
        Ledger::add($payment, 'identity_refreshed', PaymentEvent::SOURCE_SYSTEM, $payment->status, $payment->status, 'Identity updated from the current record.');
        return true;
    }
}
