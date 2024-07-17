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

$_GET['ID']            =
    (isset($_GET['ID']) && is_numeric($_GET['ID']))
        ? abs((int)$_GET['ID']) : 0;
$_SESSION['attacking'] = 0;
$ir['attacking']       = 0;
end_attack();
$r = $db->row(
    'SELECT * FROM users WHERE userid = ? LIMIT 1',
    $_GET['ID'],
);
if (!isset($_SESSION['attackwon']) || $_SESSION['attackwon'] != $_GET['ID']) {
    die("Cheaters don't get anywhere.");
}

if (!empty($r)) {
    if ($r['hp'] == 1) {
        echo 'What a cheater you are.';
    } else {
        echo "You beat {$r['username']}!!<br />
You beat {$r['username']} severely on the ground. When there is lots of blood showing, you head up to the nearest 10-story building's roof and drop him over the edge. You run home silently and carefully.";
        $_SESSION['attackwon'] = 0;
        $ga                    = null;
        $warq                  = 0;
        if ($ir['gang'] > 0 && $r['gang'] > 0) {
            $ga = $db->row(
                'SELECT gangID, gangRESPECT FROM gangs WHERE gangID = ? LIMIT 1',
                $r['gang'],
            );
            if (!empty($ga)) {
                $warq = $db->cell(
                    'SELECT COUNT(warDECLARER) FROM gangwars
                    WHERE (warDECLARER = ? AND warDECLARED = ?) 
                      OR (warDECLARED = ? AND warDECLARER = ?)',
                    $ir['gang'],
                    $r['gang'],
                    $ir['gang'],
                    $r['gang'],
                );
            }
        }
        $save = function () use ($db, $userid, $ir, $r, $ga, $warq) {
            $hosptime   = rand(50, 150) + floor($ir['level'] / 2);
            $hospreason = "Hospitalized by <a href='viewuser.php?u={$userid}'>{$ir['username']}</a>";
            $db->update(
                'users',
                [
                    'hp' => 1,
                    'hospital' => $hosptime,
                    'hospreason' => $hospreason,
                ],
                ['userid' => $r['userid']],
            );
            event_add($r['userid'], "<a href='viewuser.php?u=$userid'>{$ir['username']}</a> beat you up.");
            $db->insert(
                'attacklogs',
                [
                    'attacker' => $userid,
                    'attacked' => $_GET['ID'],
                    'result' => 'won',
                    'time' => time(),
                    'stole' => -1,
                    'attacklog' => $_SESSION['attacklog'],
                ]
            );
            if ($ir['gang'] > 0 && $r['gang'] > 0 && !empty($ga)) {
                if ($warq > 0) {
                    attack_update_gang_respect($ir['gang'], $r['gang'], 3);
                    $ga['gangRESPECT'] -= 3;
                    echo '<br />You earned 3 respect for your gang!';
                }
                // Gang Kill
                if ($ga['gangRESPECT'] <= 0 && $r['gang']) {
                    destroy_gang_and_end_wars($r['gangID']);
                }
            }
            if ($r['user_level'] == 0) {
                check_challenge_beaten($r);
            }
        };
        $db->tryFlatTransaction($save);
    }
} else {
    echo 'You beat Mr. non-existent!';
}

$h->endpage();
