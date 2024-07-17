<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

global $db, $ir, $userid, $h, $set;
require_once('globals.php');
if (!$set['sendcrys_on']) {
    die('Sorry, the game owner has disabled this feature.');
}
if (!isset($_GET['ID'])) {
    $_GET['ID'] = 0;
}
if (!isset($_POST['crystals'])) {
    $_POST['crystals'] = 0;
}
$_GET['ID']        = abs((int)$_GET['ID']);
$_POST['crystals'] = abs((int)$_POST['crystals']);
if (!((int)$_GET['ID'])) {
    echo 'Invalid User ID';
} elseif ($_GET['ID'] == $userid) {
    echo 'Haha, what does sending crystals to yourself do anyway?';
} else {
    $er = $db->row(
        'SELECT lastip, username FROM users WHERE userid = ?',
        $_GET['ID'],
    );
    if (empty($er)) {
        echo "That user doesn't exist.";
        $h->endpage();
        exit;
    }
    if ((int)$_POST['crystals']) {
        if (!isset($_POST['verf'])
            || !verify_csrf_code("sendcrys_{$_GET['ID']}",
                stripslashes($_POST['verf']))) {
            echo '<h3>Error</h3><hr />
    		This transaction has been blocked for your security.<br />
    		Please send money quickly after you open the form - do not leave it open in tabs.<br />
    		&gt; <a href="sendcrys.php?ID=' . $_GET['ID'] . '">Try Again</a>';
            $h->endpage();
            exit;
        } elseif ($_POST['crystals'] > $ir['crystals']) {
            echo 'Not enough crystals to send.';
        } else {
            $save = function () use ($db, $userid, $ir, $er) {
                $db->update(
                    'users',
                    ['crystals' => new EasyPlaceholder('crystals - ?', $_POST['crystals'])],
                    ['userid' => $userid],
                );
                $db->update(
                    'users',
                    ['crystals' => new EasyPlaceholder('crystals + ?', $_POST['crystals'])],
                    ['userid' => $_GET['ID']],
                );
                event_add($_GET['ID'],
                    "You received {$_POST['crystals']} crystals from {$ir['username']}.");
                $db->insert(
                    'crystalxferlogs',
                    [
                        'cxFROM' => $userid,
                        'cxTO' => $_GET['ID'],
                        'cxAMOUNT' => $_POST['crystals'],
                        'cxTIME' => time(),
                        'cxFROMIP' => $ir['lastip'],
                        'cxTOIP' => $er['lastip'],
                    ],
                );
            };
            $db->tryFlatTransaction($save);
            echo "You sent {$_POST['crystals']} crystals to {$er['username']} (ID {$_GET['ID']}).";
        }
    } else {
        $code = request_csrf_code("sendcrys_{$_GET['ID']}");
        echo "<h3> Sending Crystals</h3>
		You are sending crystals to <b>{$er['username']}</b> (ID {$_GET['ID']}).
		<br />You have <b>" . number_format($ir['crystals'])
            . "</b> crystals you can send.
		<form action='sendcrys.php?ID={$_GET['ID']}' method='post'>
			Crystals: <input type='text' name='crystals' /><br />
			<input type='hidden' name='verf' value='{$code}' />
			<input type='submit' value='Send' />
		</form>";
        echo "<h3>Latest 5 Transfers</h3>
		<table width='75%' cellspacing='1' class='table'>
			<tr>
				<th>Time</th>
				<th>User From</th>
				<th>User To</th>
				<th>Amount</th>
			</tr>";
        $q = $db->run(
            'SELECT cxTO, cxTIME, cxAMOUNT, u.username AS recipient
            FROM crystalxferlogs AS cx
            INNER JOIN users AS u ON cx.cxTO = u.userid
            WHERE cxFROM = ?
            ORDER BY cxTIME DESC
            LIMIT 5',
            $userid,
        );
        foreach ($q as $r) {
            echo '<tr>
            		<td>' . date('F j, Y, g:i:s a', (int)$r['cxTIME'])
                . "</td>
                    <td>{$ir['username']} [{$ir['userid']}] </td>
                    <td>{$r['recipient']} [{$r['cxTO']}] </td>
                    <td> " . number_format((int)$r['cxAMOUNT'])
                . ' crystals</td>
                  </tr>';
        }
        echo '</table>';
    }
}
$h->endpage();
