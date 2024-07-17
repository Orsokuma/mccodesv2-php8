<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

global $db, $ir, $userid, $h;
require_once('globals.php');
$_GET['ID']   =
    (isset($_GET['ID']) && is_numeric($_GET['ID']))
        ? abs(intval($_GET['ID'])) : '';
$_POST['qty'] =
    (isset($_POST['qty']) && is_numeric($_POST['qty']))
        ? abs(intval($_POST['qty'])) : '';
if (!empty($_POST['qty']) && !empty($_GET['ID'])) {
    $r = $db->row(
        'SELECT inv_qty, itmsellprice, itmid, itmname
        FROM inventory AS iv
        INNER JOIN items AS it ON iv.inv_itemid = it.itmid
        WHERE iv.inv_id = ? AND iv.inv_userid = ?
        LIMIT 1',
        $_GET['ID'],
        $userid,
    );
    if (empty($r)) {
        echo 'Invalid item ID';
    } else {
        if (!isset($_POST['verf'])
            || !verify_csrf_code("sellitem_{$_GET['ID']}",
                stripslashes($_POST['verf']))) {
            echo '<h3>Error</h3><hr />
    		This transaction has been blocked for your security.<br />
    		Please sell items quickly after you open the form - do not leave it open in tabs.<br />
    		&gt; <a href="itemsell.php?ID=' . $_GET['ID'] . '">Try Again</a>';
            $h->endpage();
            exit;
        }
        if ($_POST['qty'] > $r['inv_qty']) {
            echo 'You are trying to sell more than you have!';
        } else {
            $price = (int)($r['itmsellprice'] * $_POST['qty']);
            item_remove($userid, $r['itmid'], $_POST['qty']);
            $db->update(
                'users',
                ['money' => new ParagonIE\EasyDB\EasyPlaceholder('money + ?', $price)],
                ['userid' => $userid],
            );
            $priceh = money_formatter($price);
            echo 'You sold ' . $_POST['qty'] . ' ' . $r['itmname']
                . '(s) for ' . $priceh;
            $is_log = $ir['username'] . ' sold ' . $_POST['qty'] . ' ' . $r['itmname'] . '(s) for ' . $priceh;
            $db->insert(
                'itemselllogs',
                [
                    'isUSER' => $userid,
                    'isITEM' => $r['itmid'],
                    'isTOTALPRICE' => $price,
                    'isQTY' => $_POST['qty'],
                    'isTIME' => time(),
                    'isCONTENT' => $is_log,
                ],
            );
        }
    }
} elseif (!empty($_GET['ID']) && empty($_POST['qty'])) {
    $r = $db->row(
        'SELECT inv_qty, itmname
        FROM inventory AS iv
        INNER JOIN items AS it ON iv.inv_itemid = it.itmid
        WHERE iv.inv_id = ? AND iv.inv_userid = ?
        LIMIT 1',
        $_GET['ID'],
        $userid,
    );
    if (empty($r)) {
        echo 'Invalid item ID';
    } else {
        $code = request_csrf_code("sellitem_{$_GET['ID']}");
        echo "
		<b>Enter how many {$r['itmname']} you want to sell. You have {$r['inv_qty']} to sell.</b>
		<br />
		<form action='itemsell.php?ID={$_GET['ID']}' method='post'>
			<input type='hidden' name='verf' value='{$code}' />
			Quantity: <input type='text' name='qty' value='' />
			<br />
			<input type='submit' value='Sell Items (no prompt so be sure!' />
		</form>
   		";
    }
} else {
    echo 'Invalid use of file.';
}
$h->endpage();
