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
require dirname(__DIR__) . '/services/ListBuilder.php';
require dirname(__DIR__) . '/services/ResponseRemoval.php';

use humhub\modules\thiscoveryIncentives\services\ListBuilder;
use humhub\modules\thiscoveryIncentives\services\Money;
use humhub\modules\thiscoveryIncentives\services\NameCheck;
use humhub\modules\thiscoveryIncentives\services\ResponseRemoval;

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
check('accent', NameCheck::valid('José'));
check('curly', NameCheck::valid('O’Neil'));
check('initial', !NameCheck::valid('B.'));
check('digit', !NameCheck::valid('Mary2'));
check('short', !NameCheck::valid('A'));
check('punct first', !NameCheck::valid("'Anne"));
check('outside set', !NameCheck::valid('Łukasz'));

$parts = Money::split(100, [1, 1, 1]);
check('split count', count($parts) === 3);
check('split sum', array_sum($parts) === 100);
check('cents', Money::fromCents(1050) === '10.50');
check('to cents', Money::toCents('10.50') === 1050);

$pairs = [['code' => 'Y1', 'label' => 'I agree to receive the incentive payment for taking part in this study']];
check('opt code', ListBuilder::isOptIn('Y1', 'Y1', $pairs));
check('opt label', ListBuilder::isOptIn('Y1', 'I agree to receive the incentive payment for taking part in this study', $pairs));
check('opt text stored', ListBuilder::isOptIn('I agree to receive the incentive payment for taking part in this study', 'y1', $pairs));
check('opt other', !ListBuilder::isOptIn('N1', 'Y1', $pairs));
check('drop missing', ResponseRemoval::shouldDrop(false, false));
check('drop erased', ResponseRemoval::shouldDrop(true, true));
check('keep live', !ResponseRemoval::shouldDrop(true, false));

if ($failed > 0) {
    exit(1);
}
echo "ok\n";
