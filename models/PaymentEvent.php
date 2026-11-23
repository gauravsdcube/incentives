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
 * Append-only history for one payment. Rows are never updated.
 *
 * @property int $id
 * @property int $payment_id
 * @property string $at
 * @property int|null $actor_user_id
 * @property string $source
 * @property string $action
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $ayda_status
 * @property string|null $detail
 */
class PaymentEvent extends ActiveRecord
{
    public const SOURCE_MANAGER = 'manager';
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_AYDA = 'ayda';

    public static function tableName()
    {
        return 'incentive_payment_event';
    }

    public function getActor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'actor_user_id']);
    }
}
