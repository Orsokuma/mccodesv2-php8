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
if ($ir['energy'] < 10) {
    echo 'Sorry, it costs 10 energy to bust someone. '
        . "You only have {$ir['energy']} energy. " . 'Come back later.';
    $h->endpage();
    exit;
}
if ($ir['jail']) {
    echo 'You cannot bust out people while in jail.';
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
$mult   = $r['level'] * $r['level'];
$chance = min(($ir['crimexp'] / $mult) * 50 + 1, 95);
if (rand(1, 100) < $chance) {
    $gain = $r['level'] * 5;
    echo "You successfully busted {$r['username']} out of jail.<br />
  	&gt; <a href='jail.php'>Back</a>";
    $db->update(
        'users',
        [
            'crimexp' => new EasyPlaceholder('crimexp + ?', $gain),
            'energy' => new EasyPlaceholder('energy - 10'),
        ],
        ['userid' => $userid],
    );
    $db->update(
        'users',
        ['jail' => 0],
        ['userid' => $r['userid']],
    );
    event_add($r['userid'],
        "<a href='viewuser.php?u={$ir['userid']}'>{$ir['username']}</a> busted you out of jail.");
} else {
    echo "While trying to bust out your friend, a guard spotted you and dragged you into jail yourself. Unlucky!<br />
  	&gt; <a href='jail.php'>Back</a>";
    $time        = min($mult, 100);
    $jail_reason = "Caught trying to bust out {$r['username']}";
    $db->update(
        'users',
        [
            'jail' => $time,
            'jail_reason' => $jail_reason,
        ],
        ['userid' => $userid],
    );
    event_add($r['userid'],
        "<a href='viewuser.php?u={$ir['userid']}'>{$ir['username']}</a> was caught trying to bust you out of jail.");
}
$h->endpage();
