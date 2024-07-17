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
if (!$set['sendbank_on']) {
    die('Sorry, the game owner has disabled this feature.');
}
if (!isset($_GET['ID'])) {
    $_GET['ID'] = 0;
}
if (!isset($_POST['xfer'])) {
    $_POST['xfer'] = 0;
}
$_GET['ID']    = abs((int)$_GET['ID']);
$_POST['xfer'] = abs((int)$_POST['xfer']);
if (!((int)$_GET['ID'])) {
    echo 'Invalid User ID';
} elseif ($_GET['ID'] == $userid) {
    echo 'Haha, what does sending money to yourself do anyway?';
} else {
    $er = $db->row(
        'SELECT cybermoney, lastip, username FROM users WHERE userid = ?',
        $_GET['ID'],
    );
    if (empty($er)) {
        echo "That user doesn't exist.";
        $h->endpage();
        exit;
    }
    if ($er['cybermoney'] == -1 || $ir['cybermoney'] == -1) {
        die(
        'Sorry,you or the person you are sending to does not have a cyber bank account.');
    }
    if ((int)$_POST['xfer']) {
        if (!isset($_POST['verf'])
            || !verify_csrf_code("sendcyber_{$_GET['ID']}",
                stripslashes($_POST['verf']))) {
            echo '<h3>Error</h3><hr />
    		This transaction has been blocked for your security.<br />
    		Please send money quickly after you open the form - do not leave it open in tabs.<br />
    		&gt; <a href="sendcyber.php?ID=' . $_GET['ID'] . '">Try Again</a>';
            $h->endpage();
            exit;
        } elseif ($_POST['xfer'] > $ir['cybermoney']) {
            echo 'Not enough money to send.';
        } else {
            $save = function () use ($db, $userid, $ir, $er) {
                $db->update(
                    'users',
                    ['cybermoney' => new EasyPlaceholder('cybermoney - ?', $_POST['xfer'])],
                    ['userid' => $userid],
                );
                $db->update(
                    'users',
                    ['cybermoney' => new EasyPlaceholder('cybermoney + ?', $_POST['xfer'])],
                    ['userid' => $_GET['ID']],
                );
                event_add($_GET['ID'],
                    'You received ' . money_formatter($_POST['xfer'])
                    . " into your cyber bank account from {$ir['username']}.");
                $db->insert(
                    'bankxferlogs',
                    [
                        'cxFROM' => $userid,
                        'cxTO' => $_GET['ID'],
                        'cxAMOUNT' => $_POST['xfer'],
                        'cxTIME' => time(),
                        'cxFROMIP' => $ir['lastip'],
                        'cxTOIP' => $er['lastip'],
                        'cxBANK' => 'cyber',
                    ],
                );
            };
            $db->tryFlatTransaction($save);
            echo 'You CyberBank Transferred ' . money_formatter($_POST['xfer']) . " to {$er['username']} (ID {$_GET['ID']}).";
        }
    } else {
        $code = request_csrf_code("sendcyber_{$_GET['ID']}");
        echo "<h3>CyberBank Xfer</h3>
		You are sending cyber bank money to <b>{$er['username']}</b> (ID {$_GET['ID']}).
		<br />You have <b>" . money_formatter($ir['cybermoney'])
            . "</b> you can send.
		<form action='sendcyber.php?ID={$_GET['ID']}' method='post'>
			Money: <input type='text' name='xfer' /><br />
			<input type='hidden' name='verf' value='{$code}' />
			<input type='submit' value='Send' />
		</form>";
    }
}
$h->endpage();
