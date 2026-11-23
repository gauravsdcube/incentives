<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\models;

use humhub\components\ActiveRecord;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property string $source
 * @property string $title
 * @property int|null $form_id
 * @property int|null $space_id
 * @property string $audience
 * @property int|null $group_id
 * @property string $currency
 * @property float $default_amount
 * @property string $mode
 * @property int|null $email_field_id
 * @property int|null $first_name_field_id
 * @property int|null $last_name_field_id
 * @property int|null $opt_in_field_id
 * @property string|null $opt_in_value
 * @property string|null $ayda_project_id
 * @property string $status
 * @property int|null $created_by
 * @property string $created_at
 * @property string $updated_at
 */
class Reward extends ActiveRecord
{
    public const SOURCE_FORM = 'form';
    public const SOURCE_COMMUNITY = 'community';

    public const AUDIENCE_FORM = 'form';
    public const AUDIENCE_NETWORK = 'network';
    public const AUDIENCE_SPACE = 'space';
    public const AUDIENCE_GROUP = 'group';

    public const MODE_OPT_IN = 'opt_in';
    public const MODE_ALL = 'all_complete';
    public const MODE_MANUAL = 'manual';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public static function tableName()
    {
        return 'incentive_reward';
    }

    public function isForm(): bool
    {
        return $this->source === self::SOURCE_FORM;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function getPayments(): ActiveQuery
    {
        return $this->hasMany(Payment::class, ['reward_id' => 'id']);
    }

    public function getSpace(): ActiveQuery
    {
        return $this->hasOne(Space::class, ['id' => 'space_id']);
    }

    public function getCreator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
}
