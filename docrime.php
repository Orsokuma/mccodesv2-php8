<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

if (!isset($_GET['c'])) {
    $_GET['c'] = 0;
}
$_GET['c'] = abs((int)$_GET['c']);
$macropage = "docrime.php?c={$_GET['c']}";
global $db, $ir, $userid, $h;
$sucrate = 0;
require_once('globals.php');
if ($ir['jail'] > 0 || $ir['hospital'] > 0) {
    die('This page cannot be accessed while in jail or hospital.');
}
if ($_GET['c'] <= 0) {
    echo 'Invalid crime';
} else {
    $r = $db->row(
        'SELECT * FROM crimes WHERE crimeID = ? LIMIT 1',
        $_GET['c'],
    );
    if (empty($r)) {
        echo 'Invalid crime.';
        $h->endpage();
        exit;
    }
    if ($ir['brave'] < $r['crimeBRAVE']) {
        echo 'You do not have enough Brave to perform this crime.';
    } else {
        print $r['crimeITEXT'];
        $ir['brave'] -= $r['crimeBRAVE'];
        $save        = function () use ($db, $ir, $r, $userid) {
            $sucrate = 1;
            $ec      = '$sucrate=' . strtr($r['crimePERCFORM'], [
                    'LEVEL' => $ir['level'],
                    'CRIMEXP' => $ir['crimexp'],
                    'EXP' => $ir['exp'],
                    'WILL' => $ir['will'],
                    'IQ' => $ir['IQ'],
                ]) . ';';
            eval($ec);
            $db->update(
                'users',
                ['brave' => $ir['brave']],
                ['userid' => $userid],
            );
            if (rand(1, 100) <= $sucrate) {
                print str_replace('{money}', $r['crimeSUCCESSMUNY'], $r['crimeSTEXT']);
                $ir['money']    += $r['crimeSUCCESSMUNY'];
                $ir['crystals'] += $r['crimeSUCCESSCRYS'];
                $ir['exp']      += (int)($r['crimeSUCCESSMUNY'] / 8);
                $db->update(
                    'users',
                    [
                        'money' => $ir['money'],
                        'crystals' => $ir['crystals'],
                        'exp' => $ir['exp'],
                        'crimexp' => new EasyPlaceholder('crimexp + ?', $r['crimeXP']),
                    ],
                    ['userid' => $userid],
                );
                if ($r['crimeSUCCESSITEM']) {
                    item_add($userid, $r['crimeSUCCESSITEM'], 1);
                }
            } elseif (rand(1, 2) == 1) {
                print $r['crimeFTEXT'];
            } else {
                print $r['crimeJTEXT'];
                $db->update(
                    'users',
                    [
                        'jail' => $r['crimeJAILTIME'],
                        'jail_reason' => $r['crimeJREASON'],
                    ],
                    ['userid' => $userid],
                );
            }
        };
        $db->tryFlatTransaction($save);

        echo "<br /><a href='docrime.php?c={$_GET['c']}'>Try Again</a><br />
<a href='criminal.php'>Crimes</a>";
    }
}

$h->endpage();
