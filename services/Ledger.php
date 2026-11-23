<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;
use humhub\modules\thiscoveryIncentives\models\PaymentEvent;
use Yii;

class Ledger
{
    public static function add(
        Payment $payment,
        string $action,
        string $source,
        ?string $from,
        ?string $to,
        ?string $detail = null,
        ?string $aydaStatus = null,
        ?int $actorId = null
    ): void {
        $event = new PaymentEvent();
        $event->payment_id = (int)$payment->id;
        $event->at = date('Y-m-d H:i:s');
        $event->actor_user_id = $actorId ?? self::actor();
        $event->source = $source;
        $event->action = $action;
        $event->from_status = $from;
        $event->to_status = $to;
        $event->ayda_status = $aydaStatus;
        $event->detail = $detail !== null ? mb_substr($detail, 0, 2000) : null;
        $event->save(false);
    }

    public static function actor(): ?int
    {
        if (!Yii::$app->has('user') || Yii::$app->user->isGuest) {
            return null;
        }
        return (int)Yii::$app->user->id ?: null;
    }

    public static function touch(Payment $payment): void
    {
        $payment->updated_at = date('Y-m-d H:i:s');
    }
}
