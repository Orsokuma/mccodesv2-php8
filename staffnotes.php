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
check_access('manage_users');

$_POST['ID']         =
    (isset($_POST['ID']) && is_numeric($_POST['ID']))
        ? abs(intval($_POST['ID'])) : '';
$_POST['staffnotes'] =
    (isset($_POST['staffnotes']) && !is_array($_POST['staffnotes']))
        ? strip_tags(stripslashes($_POST['staffnotes']))
        : '';
if (empty($_POST['ID']) || empty($_POST['staffnotes'])) {
    echo 'You must enter data for this to work.
    <br />&gt; <a href="index.php">Go Home</a>';
    $h->endpage();
    exit;
}
$old = $db->cell(
    'SELECT staffnotes FROM users WHERE userid = ?',
    $_POST['ID'],
);
if (empty($old)) {
    echo 'That user does not exist.
    <br />&gt; <a href="index.php">Go Home</a>';
    $h->endpage();
    exit;
}
$db->update(
    'users',
    ['staffnotes' => $_POST['staffnotes']],
    ['userid' => $_POST['ID']],
);
$db->insert(
    'staffnotelogs',
    [
        'snCHANGER' => $userid,
        'snCHANGED' => $_POST['ID'],
        'snTIME' => time(),
        'snOLD' => $old,
        'snNEW' => $_POST['staffnotes'],
    ],
);
echo '
User notes updated!
<br />
&gt; <a href="viewuser.php?u=' . $_POST['ID']
    . '">Back To Profile</a>
 ';
$h->endpage();
