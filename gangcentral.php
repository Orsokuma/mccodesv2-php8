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
echo "<h3>Gang Central</h3>
<a href='creategang.php'>&gt; Create A Gang Here</a><br />
<hr /><u>Gang Listings</u><br />
<table cellspacing=1 class='table'>
	<tr style='background:gray;'>
		<th>Gang</th>
		<th>Members</th>
		<th>President</th>
		<th>Respect Level</th>
	</tr>";
$gq = $db->run(
    'SELECT gangID, gangNAME, gangRESPECT, userid, username
    FROM gangs AS g
    LEFT JOIN users AS u ON g.gangPRESIDENT = u.userid
    ORDER BY g.gangID'
);
foreach ($gq as $gangdata) {
    $cnt = $db->cell(
        'SELECT COUNT(userid) FROM users WHERE gang = ?',
        $gangdata['gangID']
    );
    echo "<tr>
    		<td><a href='gangs.php?action=view&ID={$gangdata['gangID']}'>{$gangdata['gangNAME']}</a></td>
    		<td>" . $cnt . "</td>
            <td><a href='viewuser.php?u={$gangdata['userid']}'>{$gangdata['username']}</a></td>
			<td>{$gangdata['gangRESPECT']}</td>
		</tr>";
}
echo '</table>';
$h->endpage();
