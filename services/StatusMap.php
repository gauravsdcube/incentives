<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;

class StatusMap
{
    public static function fromAyda(string $status): string
    {
        switch (strtolower($status)) {
            case 'released':
                return Payment::STATUS_RELEASED;
            case 'requiresreview':
                return Payment::STATUS_REQUIRES_REVIEW;
            case 'paid':
                return Payment::STATUS_PAID;
            case 'cancelled':
                return Payment::STATUS_CANCELLED;
            case 'planned':
            case 'allocated':
            default:
                return Payment::STATUS_AUTHORISED;
        }
    }
}
