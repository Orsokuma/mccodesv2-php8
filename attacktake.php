<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

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
        echo 'What a cheater u are.';
    } else {
        echo "You beat {$r['username']} ";
        $_SESSION['attackwon'] = 0;
        $qe                    = $r['level'] * $r['level'] * $r['level'];
        $expgain               = rand($qe / 2, $qe);
        $expperc               = (int)($expgain / $ir['exp_needed'] * 100);
        $ga                    = null;
        $warq                  = 0;
        if ($ir['gang'] > 0 && $r['gang'] > 0) {
            $ga = $db->row(
                'SELECT gangID, gangRESPECT FROM gangs WHERE gangID = ? LIMIT 1',
                $r['gang'],
            );
            if (!empty($ga)) {
                $warq = $db->query(
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
        echo "and gained $expperc% EXP!<br />
You hide your weapons and drop {$r['username']} off outside the hospital entrance. Feeling satisfied, you walk home.";
        $save = function () use ($db, $userid, $ir, $r, $expgain, $ga, $warq) {
            $hosptime = rand(10, 20);
            $db->update(
                'users',
                ['exp' => new EasyPlaceholder('exp + ?', $expgain)],
                ['userid' => $userid],
            );
            $hospreason = "Left by <a href='viewuser.php?u={$userid}'>{$ir['username']}</a>";
            $db->update(
                'users',
                [
                    'hp' => 1,
                    'hospital' => $hosptime,
                    'hospreason' => $hospreason,
                ],
                ['userid' => $r['userid']],
            );
            event_add($r['userid'], "<a href='viewuser.php?u=$userid'>{$ir['username']}</a> attacked you and left you lying outside the hospital.");
            $db->insert(
                'attacklog',
                [
                    'attacker' => $userid,
                    'attacked' => $r['userid'],
                    'result' => 'won',
                    'time' => time(),
                    'stole' => -2,
                    'attacklog' => $_SESSION['attacklog'],
                ]
            );
            if ($ir['gang'] > 0 && $r['gang'] > 0) {
                if (!empty($ga)) {
                    if ($warq > 0) {
                        attack_update_gang_respect($ir['gang'], $r['gang'], 1);
                        $ga['gangRESPECT'] -= 1;
                        echo '<br />You earned 1 respect for your gang!';

                    }
                    // Gang Kill
                    if ($ga['gangRESPECT'] <= 0 && $r['gang']) {
                        destroy_gang_and_end_wars($r['gang']);
                    }
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
