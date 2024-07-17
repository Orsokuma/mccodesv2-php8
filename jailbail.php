<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

global $db, $ir, $userid, $h;
require_once('globals.php');

if ($ir['jail']) {
    echo 'You cannot bail out people while in jail.';
    $h->endpage();
    exit;
}
$_GET['ID'] =
    (isset($_GET['ID']) && is_numeric($_GET['ID']))
        ? abs(intval($_GET['ID'])) : 0;
$r          = $db->row(
    'SELECT userid, jail, level, username FROM users WHERE userid = ?',
    $_GET['ID'],
);
if (empty($r)) {
    echo 'Invalid user';
    $h->endpage();
    exit;
}
if (!$r['jail']) {
    echo 'That user is not in jail!';
    $h->endpage();
    exit;
}
$cost = $r['level'] * 2000;
$cf   = money_formatter($cost);
if ($ir['money'] < $cost) {
    echo "Sorry, you do not have enough money to bail out {$r['username']}."
        . " You need {$cf}.";
    $h->endpage();
    exit;
}

echo "You successfully bailed {$r['username']} out of jail for $cf.<br />
  &gt; <a href='jail.php'>Back</a>";
$save = function () use ($db, $userid, $cost, $ir, $r) {
    $db->update(
        'users',
        ['money' => new EasyPlaceholder('money - ?', $cost)],
        ['userid' => $userid],
    );
    $db->update(
        'users',
        ['jail' => 0],
        ['userid' => $userid],
    );
    event_add($r['userid'],
        "<a href='viewuser.php?u={$ir['userid']}'>{$ir['username']}</a> bailed you out of jail.");
};
$db->tryFlatTransaction($save);
$h->endpage();
