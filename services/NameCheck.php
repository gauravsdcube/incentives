<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryIncentives\services;

/**
 * Ayda accepts a name of 2 to 128 characters. It must start with a letter,
 * contain at least two letters in a row, and otherwise use only letters,
 * spaces, apostrophes, hyphens, commas, and periods.
 */
class NameCheck
{
    public static function valid(string $name): bool
    {
        $name = trim($name);
        $length = mb_strlen($name);
        if ($length < 2 || $length > 128) {
            return false;
        }
        if (!preg_match("/^[\\p{L}][\\p{L}'\\-,. ]*$/u", $name)) {
            return false;
        }
        return (bool)preg_match('/\p{L}{2}/u', $name);
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
        if (!preg_match('/\p{L}{2}/u', $name)) {
            return 'Ayda needs at least two letters together in the name.';
        }
        return 'Ayda only accepts letters, spaces, apostrophes, hyphens, commas, and periods, and the name must start with a letter.';
    }
}
