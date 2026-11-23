<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

/**
 * Whole pence, so a fee split across people adds back to the quoted total.
 */
class Money
{
    public static function toCents($amount): int
    {
        return (int)round(((float)$amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign . intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param int[] $weights
     * @return int[]
     */
    public static function split(int $total, array $weights): array
    {
        $count = count($weights);
        if ($count === 0) {
            return [];
        }
        $sum = 0;
        foreach ($weights as $weight) {
            $sum += (int)$weight;
        }
        if ($sum <= 0) {
            return array_fill(0, $count, 0);
        }
        $out = [];
        $used = 0;
        $index = 0;
        foreach ($weights as $weight) {
            $index++;
            if ($index === $count) {
                $out[] = $total - $used;
                break;
            }
            $share = (int)floor($total * ((int)$weight) / $sum);
            $out[] = $share;
            $used += $share;
        }
        return $out;
    }
}
