<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $reward_id
 * @property string $dedupe_key
 * @property int|null $user_id
 * @property int|null $form_answer_id
 * @property int|null $wave_id
 * @property string|null $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property float $default_amount
 * @property float $amount
 * @property string $currency
 * @property int|null $amount_overridden_by
 * @property string|null $amount_overridden_at
 * @property int $excluded
 * @property string $idempotency_id
 * @property string|null $ayda_participant_id
 * @property string|null $ayda_project_participant_id
 * @property string|null $ayda_incentive_id
 * @property string|null $ayda_batch_id
 * @property float|null $fee_amount
 * @property float|null $tax_amount
 * @property string $status
 * @property string|null $ayda_status
 * @property string|null $trust_risk
 * @property int|null $selected_by
 * @property string|null $selected_at
 * @property int|null $authorised_by
 * @property string|null $authorised_at
 * @property int $identity_removed
 * @property int $forget_pending
 * @property string|null $last_error
 * @property string $created_at
 * @property string $updated_at
 */
class Payment extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FAILED = 'failed';
    public const STATUS_AUTHORISED = 'authorised';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REQUIRES_REVIEW = 'requires_review';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public static function tableName()
    {
        return 'incentive_payment';
    }

    public function getReward(): ActiveQuery
    {
        return $this->hasOne(Reward::class, ['id' => 'reward_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getEvents(): ActiveQuery
    {
        return $this->hasMany(PaymentEvent::class, ['payment_id' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    public function isUnsent(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED], true);
    }

    public function isOpenAtAyda(): bool
    {
        return in_array($this->status, [self::STATUS_AUTHORISED, self::STATUS_RELEASED, self::STATUS_REQUIRES_REVIEW], true);
    }

    public function displayName(): string
    {
        if ((int)$this->identity_removed === 1) {
            return 'Removed';
        }
        $name = trim((string)$this->first_name . ' ' . (string)$this->last_name);
        return $name !== '' ? $name : 'Unnamed';
    }

    public static function statusLabel(string $status): string
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return 'Selected, not sent';
            case self::STATUS_FAILED:
                return 'Not sent';
            case self::STATUS_AUTHORISED:
                return 'Sent to Ayda';
            case self::STATUS_RELEASED:
                return 'Ready to claim';
            case self::STATUS_REQUIRES_REVIEW:
                return 'Needs a check';
            case self::STATUS_PAID:
                return 'Paid';
            case self::STATUS_CANCELLED:
                return 'Cancelled';
            default:
                return $status;
        }
    }

    public static function participantStatusLabel(string $status): string
    {
        switch ($status) {
            case self::STATUS_RELEASED:
            case self::STATUS_AUTHORISED:
                return 'Ready to claim';
            case self::STATUS_REQUIRES_REVIEW:
                return 'Being checked';
            case self::STATUS_PAID:
                return 'Paid';
            case self::STATUS_CANCELLED:
                return 'Cancelled';
            case self::STATUS_FAILED:
                return 'Not sent';
            default:
                return 'Waiting to be sent';
        }
    }
}
