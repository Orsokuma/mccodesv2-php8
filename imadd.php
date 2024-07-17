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

$_GET['ID']        =
    (isset($_GET['ID']) && is_numeric($_GET['ID']))
        ? abs(intval($_GET['ID'])) : '';
$_POST['price']    =
    (isset($_POST['price']) && is_numeric($_POST['price']))
        ? abs(intval($_POST['price'])) : '';
$_POST['QTY']      =
    (isset($_POST['QTY']) && is_numeric($_POST['QTY']))
        ? abs(intval($_POST['QTY'])) : '';
$_POST['currency'] =
    (isset($_POST['currency'])
        && in_array($_POST['currency'], ['money', 'crystals']))
        ? $_POST['currency'] : 'money';
if ($_POST['price'] && $_POST['QTY'] && $_GET['ID']) {
    if (!isset($_POST['verf'])
        || !verify_csrf_code("imadd_{$_GET['ID']}",
            stripslashes($_POST['verf']))) {
        echo "Your request to add this item to the market has expired.
        Please try again.<br />
		&gt; <a href='imadd.php?ID={$_GET['ID']}'>Back</a>";
        $h->endpage();
        exit;
    }
    $r = $db->row(
        "SELECT inv_qty, inv_itemid, inv_id, itmname
        FROM inventory AS iv
        INNER JOIN items AS i ON iv.inv_itemid = i.itmid
        WHERE inv_id = {$_GET['ID']} AND inv_userid = $userid",
        $_GET['ID'],
        $userid,
    );
    if (empty($r)) {
        echo 'Invalid Item ID';
    } else {
        if ($r['inv_qty'] < $_POST['QTY']) {
            echo 'You do not have enough of this item.';
            $h->endpage();
            exit;
        }
        $cqty = $db->row(
            'SELECT imID FROM itemmarket WHERE imITEM = %u AND imPRICE = %u AND imADDER = %u AND imCURRENCY = "%s"',
            $r['inv_itemid'],
            $_POST['price'],
            $userid,
            $_POST['currency']
        );
        $save = function () use ($db, $ir, $r, $userid, $cqty) {
            if (!empty($cqty)) {
                $db->update(
                    'itemmarket',
                    ['imQTY' => new EasyPlaceholder('imQTY + ?', $_POST['QTY'])],
                    ['imID' => $cqty['imID']]
                );
            } else {
                $db->insert(
                    'itemmarket',
                    [
                        'imITEM' => $r['inv_itemid'],
                        'imADDER' => $userid,
                        'imPRICE' => $_POST['price'],
                        'imCURRENCY' => $_POST['currency'],
                        'imQTY' => $_POST['QTY'],
                    ],
                );
            }
            item_remove($userid, $r['inv_itemid'], $_POST['QTY']);
            $imadd_log = "{$ir['username']} added {$r['itmname']} x{$_POST['QTY']} to the item market for {$_POST['price']} {$_POST['currency']}";
            $db->insert(
                'imarketaddlogs',
                [
                    'imaITEM' => $r['inv_itemid'],
                    'imaPRICE' => $_POST['price'],
                    'imaINVID' => $r['inv_id'],
                    'imaADDER' => $userid,
                    'imaTIME' => time(),
                    'imaCONTENT' => $imadd_log,
                ],
            );
        };
        $db->tryFlatTransaction($save);
        echo 'Item added to market.';
    }
} else {
    $exists = $db->exists(
        'SELECT COUNT(inv_id) FROM inventory WHERE inv_id = ? AND inv_userid = ?',
        $_GET['ID'],
        $userid,
    );
    if (!$exists) {
        echo 'Invalid Item ID';
    } else {
        $imadd_csrf = request_csrf_code("imadd_{$_GET['ID']}");
        echo "
Adding an item to the item market...<br />
	<form action='imadd.php?ID={$_GET['ID']}' method='post'>
	<input type='hidden' name='verf' value='{$imadd_csrf}' />
		Quantity: <input type='text' name='QTY' value=''><br />
		Price: <input type='text' name='price' value='0' /><br />
	<select name='currency' type='dropdown'>
		<option value='money'>Money</option>
		<option value='crystals'>Crystals</option>
	</select><br />
		<input type='submit' value='Add' />
	</form>
   ";
    }
}
$h->endpage();
