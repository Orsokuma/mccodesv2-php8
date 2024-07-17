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
check_access('manage_punishments');
$_POST['user']   =
    (isset($_POST['user']) && is_numeric($_POST['user']))
        ? abs(intval($_POST['user'])) : '';
$_POST['reason'] =
    (isset($_POST['reason'])
        && ((strlen($_POST['reason']) > 3)
            && (strlen($_POST['reason']) < 50)))
        ? strip_tags(stripslashes($_POST['reason'])) : '';
$_POST['days']   =
    (isset($_POST['days']) && is_numeric($_POST['days']))
        ? abs(intval($_POST['days'])) : '';
if (!empty($_POST['user']) && !empty($_POST['reason'])
    && !empty($_POST['days'])) {
    if (!isset($_POST['verf'])
        || !verify_csrf_code('jailuser', stripslashes($_POST['verf']))) {
        echo '<h3>Error</h3><hr />
   			This operation has been blocked for your security.<br />
    		Please try again.<br />
    		&gt; <a href="jailuser.php?userid=' . $_POST['user']
            . '">Try Again</a>';
        $h->endpage();
        exit;
    }
    if (check_access('administrator', false, $_POST['user'])) {
        echo 'You cannot fed admins, please destaff them first.
        <br />&gt; <a href="jailuser.php">Go Back</a>';
        $h->endpage();
        exit;
    }
    $db->update(
        'users',
        ['fedjail' => 1],
        ['userid' => $_POST['user']],
    );
    $db->insert(
        'fedjail',
        [
            'fed_userid' => $_POST['user'],
            'fed_days' => $_POST['days'],
            'fed_jailedby' => $userid,
            'fed_reason' => $_POST['reason'],
        ],
    );
    $db->insert(
        'jaillogs',
        [
            'jaJAILER' => $userid,
            'jaJAILED' => $_POST['user'],
            'jaDAYS' => $_POST['days'],
            'jaREASON' => $_POST['reason'],
            'jaTIME' => time(),
        ],
    );
    echo 'User was fedded.<br />
    &gt; <a href="index.php">Go Home</a>';
} else {
    $jail_csrf      = request_csrf_code('jailuser');
    $_GET['userid'] =
        (isset($_GET['userid']) && is_numeric($_GET['userid']))
            ? abs(intval($_GET['userid'])) : -1;
    echo "
	<h3>Jailing User</h3>
	The user will be put in fed jail and will be unable to do anything in the game.
	<br />
	<form action='jailuser.php' method='post'>
		User: " . user_dropdown('user', $_GET['userid'])
        . "
		<br />
		Days: <input type='text' name='days' />
		<br />
		Reason: <input type='text' name='reason' />
		<br />
		<input type='hidden' name='verf' value='{$jail_csrf}' />
		<input type='submit' value='Jail User' />
	</form>
   	";
}
$h->endpage();
