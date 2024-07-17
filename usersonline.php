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
echo '<h3>Users Online</h3>';
$cn          = 0;
$expiry_time = time() - 900;
$q           = $db->run(
    'SELECT userid, username, laston FROM users WHERE laston > ? ORDER BY laston DESC',
    $expiry_time,
);
foreach ($q as $r) {
    $cn++;
    echo $cn . '. <a href="viewuser.php?u=' . $r['userid'] . '">'
        . $r['username'] . '</a> (' . datetime_parse($r['laston'])
        . ')
	<br />
   	';
}
$h->endpage();
