<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */


/**
 * Standalone checks for name rules and fee splitting. No HumHub bootstrap.
 */

require dirname(__DIR__) . '/services/NameCheck.php';
require dirname(__DIR__) . '/services/Money.php';

use humhub\modules\thiscoveryIncentives\services\Money;
use humhub\modules\thiscoveryIncentives\services\NameCheck;

$failed = 0;
function check($label, $condition)
{
    global $failed;
    if (!$condition) {
        echo "FAIL {$label}\n";
        $failed++;
    }
}

check('mary', NameCheck::valid('Mary'));
check('mary ann', NameCheck::valid('Mary Ann'));
check("o'neil", NameCheck::valid("O'Neil"));
check('hyphen', NameCheck::valid('Anne-Marie'));
check('initial', !NameCheck::valid('B.'));
check('digit', !NameCheck::valid('Mary2'));
check('short', !NameCheck::valid('A'));
check('punct first', !NameCheck::valid("'Anne"));

$parts = Money::split(100, [1, 1, 1]);
check('split count', count($parts) === 3);
check('split sum', array_sum($parts) === 100);
check('cents', Money::fromCents(1050) === '10.50');
check('to cents', Money::toCents('10.50') === 1050);

if ($failed > 0) {
    exit(1);
}
echo "ok\n";
