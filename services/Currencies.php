<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

class Currencies
{
    public const CODES = [
        'AED', 'ALL', 'ANG', 'ARS', 'AUD', 'AZN', 'BAM', 'BBD', 'BGN', 'BHD', 'BOB', 'BRL', 'BSD', 'BYN', 'BZD',
        'CAD', 'CHF', 'CLP', 'CNY', 'COP', 'CUP', 'CVE', 'CZK', 'DKK', 'DOP', 'EGP', 'EUR', 'GBP', 'GEL', 'GHS',
        'GIP', 'GTQ', 'HKD', 'HRK', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK', 'JOD', 'JPY', 'KES', 'KRW',
        'KWD', 'KZT', 'LBP', 'LKR', 'LYD', 'MAD', 'MDL', 'MKD', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'NGN',
        'NOK', 'NPR', 'NZD', 'OMR', 'PEN', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RSD', 'SAR', 'SCR', 'SDG', 'SEK',
        'SGD', 'SSP', 'STN', 'THB', 'TND', 'TRY', 'TWD', 'UAH', 'UGX', 'USD', 'VED', 'VND', 'XCD', 'XOF', 'XPF', 'ZAR',
    ];

    public static function valid(string $code): bool
    {
        return in_array(strtoupper($code), self::CODES, true);
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::CODES as $code) {
            $options[$code] = $code;
        }
        return $options;
    }
}
