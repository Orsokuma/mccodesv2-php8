<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

$atkpage = 1;
global $db, $ir, $userid, $h;
require_once('globals.php');

$_GET['ID']             =
    (isset($_GET['ID']) && is_numeric($_GET['ID']))
        ? abs((int)$_GET['ID']) : 0;
$_SESSION['attacking']  = 0;
$_SESSION['attacklost'] = 0;
$r                      = $db->row(
    'SELECT userid, username, level, gang FROM users WHERE userid = ? LIMIT 1',
    $_GET['ID'],
);
if (!empty($r)) {
    echo "You lost to {$r['username']}";
    $expgain  = abs(($ir['level'] - $r['level']) ^ 3);
    $expgainp = $expgain / $ir['exp_needed'] * 100;
    echo " and lost $expgainp% EXP!";
    // Figure out their EXP, 0 or decreased?
    $newexp = max($ir['exp'] - $expgain, 0);
    $db->update(
        'users',
        [
            'exp' => $newexp,
            'attacking' => 0,
        ],
        ['userid' => $userid],
    );
    event_add($r['userid'],
        "<a href='viewuser.php?u=$userid'>{$ir['username']}</a> attacked you and lost.");
    $db->insert(
        'attacklogs',
        [
            'attacker' => $userid,
            'attacked' => $r['userid'],
            'result' => 'lost',
            'time' => time(),
            'stole' => 0,
            'attacklog' => $_SESSION['attacklog'],
        ]
    );
    if ($ir['gang'] > 0 && $r['gang'] > 0) {
        $war = $db->cell(
            'SELECT COUNT(*) FROM gangwars
                        WHERE (warDECLARER = ? AND warDECLARED = ?)
                           OR (warDECLARED = ? AND warDECLARER = ?)',
            $ir['gang'],
            $r['gang'],
            $ir['gang'],
            $r['gang'],
        );
        if ($war > 0) {
            attack_update_gang_respect($r['gang'], $ir['gang'], 1);
            echo '<br />You lost 1 respect for your gang!';
        }
    }
} else {
    echo 'You lost to Mr. Non-existent! =O';
}
$h->endpage();
