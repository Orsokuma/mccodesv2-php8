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
echo "<h3>Battle Tent</h3>
<b>Welcome to the battle tent! Here you can challenge NPCs for money.</b>
<table width=100% cellspacing=1 class='table'>
	<tr style='background: gray; '>
		<th>Bot Name</th>
		<th>Level</th>
		<th>Times Owned</th>
		<th>Ready To Be Challenged?</th>
		<th>Location</th>
		<th>Money Won</th>
		<th>Challenge</th>
	</tr>";
$q = $db->run(
    'SELECT cb.cb_money, c.npcid, cy.cityname, u.userid, username, level, hp, maxhp, location, hospital, jail
    FROM challengebots AS cb
    LEFT JOIN users AS u ON cb.cb_npcid = u.userid
    LEFT JOIN challengesbeaten AS c ON c.npcid = u.userid AND c.userid = ?
    LEFT JOIN cities AS cy ON u.location = cy.cityid',
    $userid,
);
foreach ($q as $r) {
    $earn  = $r['cb_money'];
    $v     = $r['userid'];
    $times = $db->cell(
        'SELECT COUNT(npcid) FROM challengesbeaten WHERE npcid = ?',
        $v,
    );
    echo "<tr><td>{$r['username']}</td><td>{$r['level']}</td><td>$times</td><td>";
    if ($r['hp'] >= $r['maxhp'] / 2 && $r['location'] == $ir['location']
        && !$ir['hospital'] && !$ir['jail'] && !$r['hospital']
        && !$r['jail']) {
        echo '<font color=green>Yes</font>';
    } else {
        echo '<font color=red>No</font>';
    }
    echo "</td><td>{$r['cityname']}</td><td>$earn</td><td>";
    if ($r['npcid']) {
        echo '<i>Already</i>';
    } else {
        echo "<a href='attack.php?ID={$r['userid']}'>Challenge</a>";
    }
    echo '</td></tr>';
}
echo '</table>';
$h->endpage();
