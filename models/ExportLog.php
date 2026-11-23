<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\models;

use humhub\components\ActiveRecord;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $space_id
 * @property int $row_count
 * @property string|null $filters_json
 * @property string $created_at
 */
class ExportLog extends ActiveRecord
{
    public static function tableName()
    {
        return 'incentive_export_log';
    }
}
