<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

/**
 * Ayda accepts a name of 2 to 128 characters. It must start with a Latin letter,
 * contain at least two letters in a row, and otherwise use only the Latin letters
 * Ayda lists, spaces, apostrophes, hyphens, commas, and periods.
 */
class NameCheck
{
    /**
     * Letters accepted by Ayda PersonsName, without the multiplication sign that
     * sits inside that pattern's character range.
     */
    private const LETTERS = 'A-Za-zŽžÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐĎÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöùúûüýšćþÿ';

    public static function valid(string $name): bool
    {
        $name = trim($name);
        $length = mb_strlen($name);
        if ($length < 2 || $length > 128) {
            return false;
        }
        $letters = self::LETTERS;
        if (!preg_match('/^[' . $letters . "][" . $letters . "'’\\-,. ]*$/u", $name)) {
            return false;
        }
        return (bool)preg_match('/[' . $letters . ']{2}/u', $name);
    }

    public static function problem(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'A first name and a last name are required.';
        }
        if (mb_strlen($name) < 2) {
            return 'Ayda needs at least two characters in the name.';
        }
        if (mb_strlen($name) > 128) {
            return 'Ayda accepts at most 128 characters in a name.';
        }
        if (!preg_match('/[' . self::LETTERS . ']{2}/u', $name)) {
            return 'Ayda needs at least two letters together in the name.';
        }
        return 'Ayda only accepts Latin letters, spaces, apostrophes, hyphens, commas, and periods, and the name must start with a letter.';
    }
}
