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
        echo 'What a cheater you are.';
    } else {
        $stole = (int)round($r['money'] / (rand(200, 5000) / 10));
        echo "You beat {$r['username']}!!<br />
		You knock {$r['username']} on the floor a few times to make sure he is unconscious, "
            . 'then open his wallet, snatch ' . money_formatter($stole)
            . ', and run home happily.';
        $hosptime = rand(20, 40) + floor($ir['level'] / 8);
        $expgain  = 0;
        $db->update(
            'users',
            [
                'exp' => new EasyPlaceholder('exp + ?', $expgain),
                'money' => new EasyPlaceholder('money + ?', $stole),
            ],
            ['userid' => $userid],
        );
        $hospreason = "Mugged by <a href='viewuser.php?u={$userid}'>{$ir['username']}</a>";
        $db->update(
            'users',
            [
                'hp' => 1,
                'money' => new EasyPlaceholder('money - ?', $stole),
                'hospital' => $hosptime,
                'hospreason' => $hospreason,
            ],
            ['userid' => $r['userid']],
        );
        event_add($r['userid'],
            "<a href='viewuser.php?u=$userid'>{$ir['username']}</a> mugged you and stole "
            . money_formatter($stole) . '.');
        $db->insert(
            'attacklogs',
            [
                'attacker' => $userid,
                'attacked' => $_GET['ID'],
                'result' => 'won',
                'time' => time(),
                'stole' => $stole,
                'attacklog' => $_SESSION['attacklog'],
            ],
        );
        $_SESSION['attackwon'] = 0;
        if ($ir['gang'] > 0 && $r['gang'] > 0) {
            $ga = $db->row(
                'SELECT gangID, gangRESPECT FROM gangs WHERE gangID = ? LIMIT 1',
                $r['gang'],
            );
            if (!empty($ga)) {
                $war = $db->cell(
                    'SELECT COUNT(warDECLARER) FROM gangwars
                          WHERE (warDECLARER = ? AND warDECLARED = ?)
                             OR (warDECLARED = ? AND warDECLARER = ?)',
                    $ir['gang'],
                    $r['gang'],
                    $ir['gang'],
                    $r['gang'],
                );
                if ($war > 0) {
                    attack_update_gang_respect($ir['gang'], $r['gang'], 2);
                    $ga['gangRESPECT'] -= 2;
                    echo '<br />You earned 2 respect for your gang!';

                }
                //Gang Kill
                if ($ga['gangRESPECT'] <= 0 && $r['gang']) {
                    destroy_gang_and_end_wars($r['gang']);
                }
            }
        }

        if ($r['user_level'] == 0) {
            check_challenge_beaten($r);
        }

    }
} else {
    echo 'You beat Mr. non-existent! Haha, pwned!';
}
$h->endpage();
