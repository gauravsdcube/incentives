<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\Payment;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class RegisterQuery
{
    public string $status = '';
    public string $source = '';
    public string $q = '';
    public string $from = '';
    public string $to = '';
    public ?int $rewardId = null;
    public ?int $spaceId = null;

    public function query(): ActiveQuery
    {
        $query = Payment::find()->alias('p')->joinWith(['reward r']);
        if ($this->spaceId) {
            $query->andWhere(['r.space_id' => $this->spaceId]);
        }
        if ($this->rewardId) {
            $query->andWhere(['p.reward_id' => $this->rewardId]);
        }
        if ($this->status !== '') {
            $query->andWhere(['p.status' => $this->status]);
        }
        if ($this->source !== '') {
            $query->andWhere(['r.source' => $this->source]);
        }
        if ($this->from !== '') {
            $query->andWhere(['>=', 'p.created_at', $this->from . ' 00:00:00']);
        }
        if ($this->to !== '') {
            $query->andWhere(['<=', 'p.created_at', $this->to . ' 23:59:59']);
        }
        if ($this->q !== '') {
            $query->andWhere([
                'or',
                ['like', 'p.email', $this->q],
                ['like', 'p.first_name', $this->q],
                ['like', 'p.last_name', $this->q],
                ['like', 'r.title', $this->q],
            ]);
        }
        return $query;
    }

    public function provider(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->query()->orderBy(['p.id' => SORT_DESC]),
            'pagination' => ['pageSize' => 50],
        ]);
    }

    /**
     * @return array{count:int,authorised:string,fees:string,tax:string,paid:string,unclaimed:string,cancelled:string}
     */
    public function totals(): array
    {
        $rows = $this->query()->select(['p.status', 'p.amount', 'p.fee_amount', 'p.tax_amount'])->asArray()->all();
        $count = 0;
        $authorised = 0;
        $fees = 0;
        $tax = 0;
        $paid = 0;
        $unclaimed = 0;
        $cancelled = 0;
        $sent = [Payment::STATUS_AUTHORISED, Payment::STATUS_RELEASED, Payment::STATUS_REQUIRES_REVIEW, Payment::STATUS_PAID];
        foreach ($rows as $row) {
            $count++;
            $amount = Money::toCents($row['amount'] ?? 0);
            $fees += Money::toCents($row['fee_amount'] ?? 0);
            $tax += Money::toCents($row['tax_amount'] ?? 0);
            if (in_array($row['status'], $sent, true)) {
                $authorised += $amount;
            }
            if ($row['status'] === Payment::STATUS_PAID) {
                $paid += $amount;
            }
            if (in_array($row['status'], [Payment::STATUS_AUTHORISED, Payment::STATUS_RELEASED, Payment::STATUS_REQUIRES_REVIEW], true)) {
                $unclaimed += $amount;
            }
            if ($row['status'] === Payment::STATUS_CANCELLED) {
                $cancelled += $amount;
            }
        }
        return [
            'count' => $count,
            'authorised' => Money::fromCents($authorised),
            'fees' => Money::fromCents($fees),
            'tax' => Money::fromCents($tax),
            'paid' => Money::fromCents($paid),
            'unclaimed' => Money::fromCents($unclaimed),
            'cancelled' => Money::fromCents($cancelled),
        ];
    }

    public function filters(): array
    {
        return [
            'status' => $this->status,
            'source' => $this->source,
            'q' => $this->q,
            'from' => $this->from,
            'to' => $this->to,
            'rewardId' => $this->rewardId,
            'spaceId' => $this->spaceId,
        ];
    }
}
