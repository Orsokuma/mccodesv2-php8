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
$mp = $db->row(
    'SELECT * FROM houses WHERE hWILL = ? LIMIT 1',
    $ir['maxwill'],
);
if (isset($_GET['property']) && is_numeric($_GET['property'])) {
    $_GET['property'] = abs((int)$_GET['property']);
    $np               = $db->row(
        'SELECT hWILL, hPRICE, hNAME FROM houses WHERE hID = ?',
        $_GET['property'],
    );
    if (empty($np)) {
        echo "That house doesn't exist.";
        $h->endpage();
        exit;
    }
    if ($np['hWILL'] < $mp['hWILL']) {
        echo 'You cannot go backwards in houses!';
    } elseif ($np['hPRICE'] > $ir['money']) {
        echo "You do not have enough money to buy the {$np['hNAME']}.";
    } else {
        $db->update(
            'users',
            [
                'money' => new EasyPlaceholder('money - ?', $np['hPRICE']),
                'will' => 0,
                'maxwill' => $np['hWILL'],
            ],
            ['userid' => $userid],
        );
        echo "Congrats, you bought the {$np['hNAME']} for "
            . money_formatter($np['hPRICE']) . '!';
    }
} elseif (isset($_GET['sellhouse'])) {
    if ($ir['maxwill'] == 100) {
        echo 'You already live in the lowest property!';
    } else {
        $db->update(
            'users',
            [
                'money' => new EasyPlaceholder('money + ?', $mp['hPRICE']),
                'will' => 0,
                'maxwill' => 100,
            ],
            ['userid' => $userid],
        );
        echo "You sold your {$mp['hNAME']} and went back to your shed.";
    }
} else {
    echo "Your current property: <b>{$mp['hNAME']}</b><br />
The houses you can buy are listed below. Click a house to buy it.<br />";
    if ($ir['maxwill'] > 100) {
        echo "<a href='estate.php?sellhouse'>Sell Your House</a><br />";
    }
    $hq = $db->run(
        'SELECT * FROM houses WHERE hWILL > ? ORDER BY hWILL',
        $ir['maxwill'],
    );
    foreach ($hq as $r) {
        echo "<a href='estate.php?property={$r['hID']}'>{$r['hNAME']}</a>"
            . '&nbsp;&nbsp - Cost: ' . money_formatter($r['hPRICE'])
            . "&nbsp;&nbsp - Will Bar: {$r['hWILL']}<br />";
    }
}
$h->endpage();
