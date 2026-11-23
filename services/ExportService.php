<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

use humhub\modules\thiscoveryIncentives\models\ExportLog;
use humhub\modules\thiscoveryIncentives\models\Payment;
use Yii;

class ExportService
{
    public function csv(RegisterQuery $query): string
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Payment id', 'Created', 'Reward', 'Source', 'Wave', 'Person', 'Email',
            'Currency', 'Default amount', 'Amount', 'Overridden by', 'Overridden at',
            'Fee', 'VAT', 'Status', 'Ayda status', 'Ayda incentive id', 'Authorised by', 'Authorised at',
        ]);
        $count = 0;
        foreach ($query->query()->with(['reward'])->orderBy(['p.id' => SORT_ASC])->each(200) as $payment) {
            /** @var Payment $payment */
            $removed = (int)$payment->identity_removed === 1;
            fputcsv($handle, [
                $payment->id,
                $payment->created_at,
                $payment->reward ? $payment->reward->title : '',
                $payment->reward ? $payment->reward->source : '',
                $payment->wave_id,
                $removed ? 'Removed' : $payment->displayName(),
                $removed ? '' : $payment->email,
                $payment->currency,
                $payment->default_amount,
                $payment->amount,
                $payment->amount_overridden_by,
                $payment->amount_overridden_at,
                $payment->fee_amount,
                $payment->tax_amount,
                Payment::statusLabel((string)$payment->status),
                $payment->ayda_status,
                $payment->ayda_incentive_id,
                $payment->authorised_by,
                $payment->authorised_at,
            ]);
            $count++;
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $log = new ExportLog();
        $log->user_id = Ledger::actor();
        $log->space_id = $query->spaceId;
        $log->row_count = $count;
        $log->filters_json = json_encode($query->filters());
        $log->created_at = date('Y-m-d H:i:s');
        $log->save(false);

        Yii::info('Incentive register exported: ' . $count . ' rows', 'thiscovery-incentives');
        return $csv === false ? '' : $csv;
    }
}
