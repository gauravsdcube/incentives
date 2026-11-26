<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use humhub\modules\thiscoveryIncentives\models\Reward;
use Yii;
use yii\db\Query;

/**
 * An erased survey response leaves the reward list when it has not been sent.
 * A payment already sent stays, with its amount and status history.
 */
class ResponseRemoval
{
    public static function forgetAnswer(int $answerId): void
    {
        if ($answerId < 1) {
            return;
        }
        $rows = Payment::find()
            ->where(['form_answer_id' => $answerId])
            ->andWhere(['status' => [Payment::STATUS_DRAFT, Payment::STATUS_FAILED]])
            ->all();
        foreach ($rows as $payment) {
            self::deleteUnsent($payment);
        }
    }

    public static function sweep(Reward $reward): int
    {
        if (!$reward->isForm()) {
            return 0;
        }
        $rows = Payment::find()
            ->where(['reward_id' => (int)$reward->id])
            ->andWhere(['status' => [Payment::STATUS_DRAFT, Payment::STATUS_FAILED]])
            ->andWhere(['not', ['form_answer_id' => null]])
            ->all();
        if ($rows === []) {
            return 0;
        }
        $ids = [];
        foreach ($rows as $payment) {
            $ids[] = (int)$payment->form_answer_id;
        }
        $ids = array_values(array_unique($ids));
        $alive = self::livingAnswers($ids);
        $erased = self::erasedAnswers($ids);
        $removed = 0;
        foreach ($rows as $payment) {
            $id = (int)$payment->form_answer_id;
            if (self::shouldDrop(isset($alive[$id]), isset($erased[$id]))) {
                self::deleteUnsent($payment);
                $removed++;
            }
        }
        return $removed;
    }

    public static function shouldDrop(bool $answerExists, bool $erased): bool
    {
        return !$answerExists || $erased;
    }

    /**
     * @param int[] $ids
     * @return array<int, bool>
     */
    private static function livingAnswers(array $ids): array
    {
        if (!class_exists(\humhub\modules\thiscoveryForms\models\FormAnswer::class)) {
            return array_fill_keys($ids, true);
        }
        $found = \humhub\modules\thiscoveryForms\models\FormAnswer::find()->select('id')->where(['id' => $ids])->column();
        return array_fill_keys(array_map('intval', $found), true);
    }

    /**
     * @param int[] $ids
     * @return array<int, bool>
     */
    private static function erasedAnswers(array $ids): array
    {
        $schema = Yii::$app->db->schema->getTableSchema('{{%custom_form_erasure}}', true);
        if ($schema === null || !isset($schema->columns['answer_id'])) {
            return [];
        }
        $found = (new Query())
            ->select('answer_id')
            ->from('{{%custom_form_erasure}}')
            ->where(['answer_id' => $ids])
            ->column();
        $out = [];
        foreach ($found as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $out[$id] = true;
            }
        }
        return $out;
    }

    private static function deleteUnsent(Payment $payment): void
    {
        if (!$payment->isUnsent()) {
            return;
        }
        PaymentEvent::deleteAll(['payment_id' => (int)$payment->id]);
        $payment->delete();
    }
}
