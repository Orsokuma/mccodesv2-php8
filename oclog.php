<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

global $db, $h;
require_once('globals.php');
$_GET['ID'] =
    (isset($_GET['ID']) && is_numeric($_GET['ID']))
        ? abs(intval($_GET['ID'])) : 0;
if (empty($_GET['ID'])) {
    echo 'Invalid command.<br />
    &gt; <a href="index.php">Go Home</a>';
    $h->endpage();
    exit;
}
$r = $db->row(
    'SELECT ocCRIMEN, ocTIME, oclLOG, oclRESULT, oclMONEY FROM oclogs WHERE oclID = ?',
    $_GET['ID'],
);
if (empty($r)) {
    echo 'Invalid OC.<br />
    &gt; <a href="index.php">Go Home</a>';
    $h->endpage();
    exit;
}
echo "
Here is the detailed view on this crime.
<br />
<b>Crime:</b> {$r['ocCRIMEN']}
<br />
<b>Time Executed:</b> " . date('F j, Y, g:i:s a', (int)$r['ocTIME'])
    . "
<br />
        {$r['oclLOG']}
<br />
<br />
<b>Result:</b> {$r['oclRESULT']}
<br />
<b>Money Made:</b> " . money_formatter((int)$r['oclMONEY']) . '
   ';
$h->endpage();
