<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;
use ParagonIE\EasyDB\EasyStatement;

global $db, $ir, $h;
require_once('globals.php');

/**
 * @param $goBackTo
 * @return void
 */
function csrf_error($goBackTo): void
{
    global $h;
    echo '<h3>Error</h3><hr />
    Your action has been blocked for your security.<br />
    Please make gang actions quickly after you open the form
    	- do not leave it open in tabs.<br />
    &gt; <a href="yourgang.php?action=' . $goBackTo . '">Try Again</a>';
    $h->endpage();
    exit;
}

/**
 * @param $formid
 * @param $goBackTo
 * @return void
 */
function csrf_stdverify($formid, $goBackTo): void
{
    if (!isset($_POST['verf'])
        || !verify_csrf_code($formid, stripslashes($_POST['verf']))) {
        csrf_error($goBackTo);
    }
}

if (!$ir['gang']) {
    echo "You're not in a gang.";
} else {
    $gangdata = $db->row(
        'SELECT g.*, oc.*
        FROM gangs AS g
        LEFT JOIN orgcrimes AS oc ON g.gangCRIME = oc.ocID
        WHERE g.gangID = ?',
        $ir['gang'],
    );
    if (empty($gangdata)) {
        echo "Error: Your gang has been deleted.<br />
        &gt; <a href='index.php'>Home</a>";
        $h->endpage();
        exit;
    }
    echo "
	<h3><u>Your Gang - {$gangdata['gangNAME']}</u></h3>
   	";
    $war_count = $db->cell(
        'SELECT COUNT(warID) FROM gangwars WHERE warDECLARER = ? OR warDECLARED = ?',
        $ir['gang'],
        $ir['gang'],
    );
    if ($war_count) {
        echo "
		<h3>
			<a href='yourgang.php?action=warview'>
			<span style='color: red;'>Your gang is currently in "
            . $war_count
            . ' war(s).</span>
            </a>
        </h3>
   		';
    }
    if (!isset($_GET['action'])) {
        $_GET['action'] = '';
    }
    switch ($_GET['action']) {
        case 'summary':
            gang_summary();
            break;
        case 'members':
            gang_memberlist();
            break;
        case 'kick':
            gang_staff_kick();
            break;
        case 'forums':
            gang_forums();
            break;
        case 'donate':
            gang_donate();
            break;
        case 'donate2':
            gang_donate2();
            break;
        case 'warview':
            gang_warview();
            break;
        case 'staff':
            gang_staff();
            break;
        case 'leave':
            gang_leave();
            break;
        case 'atklogs':
            gang_atklogs();
            break;
        case 'crimes':
            gang_crimes();
            break;
        default:
            gang_index();
            break;
    }
}

/**
 * @return void
 */
function gang_index(): void
{
    global $db, $ir, $userid, $gangdata;
    echo "
    <table cellspacing=1 class='table'>
    		<tr>
    			<td><a href='yourgang.php?action=summary'>Summary</a></td>
    			<td><a href='yourgang.php?action=donate'>Donate</a></td>
    		</tr>
    		<tr>
    			<td><a href='yourgang.php?action=members'>Members</a></td>
    			<td><a href='yourgang.php?action=crimes'>Crimes</a></td>
    		</tr>
    		<tr>
    			<td><a href='yourgang.php?action=forums'>Forums</a></td>
    			<td><a href='yourgang.php?action=leave'>Leave</a></td>
    		</tr>
    		<tr>
    			<td><a href='yourgang.php?action=atklogs'>Attack Logs</a></td>
    			<td>
       ";
    if ($gangdata['gangPRESIDENT'] == $userid
        || $gangdata['gangVICEPRES'] == $userid) {
        echo "<a href='yourgang.php?action=staff&amp;act2=idx'>Staff Room</a>";
    } else {
        echo '&nbsp;';
    }
    echo "
				</td>
			</tr>
	</table>
	<br />
	<table cellspacing='1' class='table'>
		<tr>
			<td align='center' class='h'>Gang Announcement</td>
		</tr>
		<tr>
			<td bgcolor='#DDDDDD'>{$gangdata['gangAMENT']}</td>
		</tr>
	</table>
	<br />
	<b>Last 10 Gang Events</b>
	<br />
   	";
    $q = $db->run(
        'SELECT gevTIME, gevTEXT FROM gangevents WHERE gevGANG = ? ORDER BY gevTIME DESC LIMIT 10',
        $ir['gang'],
    );
    echo "
	<table width='75%' cellspacing='1' class='table'>
		<tr>
			<th>Time</th>
			<th>Event</th>
		</tr>
   	";
    foreach ($q as $r) {
        echo '
		<tr>
			<td>' . date('F j Y, g:i:s a', (int)$r['gevTIME'])
            . "</td>
			<td>{$r['gevTEXT']}</td>
		</tr>
   		";
    }
    echo '</table>';
}

/**
 * @return void
 */
function gang_summary(): void
{
    global $db, $gangdata;
    echo '
    <b>General</b>
    <br />
       ';
    $ldrnm = $db->cell(
        'SELECT username FROM users WHERE userid = ?',
        $gangdata['gangPRESIDENT'],
    );
    if (!empty($ldrnm)) {
        echo "President:
        	<a href='viewuser.php?u={$gangdata['gangPRESIDENT']}'>
        	{$ldrnm}
        	</a><br />";
    } else {
        echo 'President: None<br />';
    }
    $vldrnm = $db->cell(
        'SELECT username FROM users WHERE userid = ?',
        $gangdata['gangVICEPRES'],
    );
    if (!empty($vldrnm)) {
        echo "Vice-President:
        	<a href='viewuser.php?u={$gangdata['gangVICEPRES']}'>
        	{$vldrnm}
        	</a><br />";
    } else {
        echo 'Vice-President: None<br />';
    }
    $cnt = $db->cell(
        'SELECT COUNT(userid) FROM users WHERE gang = ?',
        $gangdata['gangID'],
    );
    echo '
    Members: ' . $cnt . "
    <br />
    Capacity: {$gangdata['gangCAPACITY']}
    <br />
    Respect Level: {$gangdata['gangRESPECT']}
    <hr />
    <b>Financial:</b>
    <br />
    Money in vault: " . money_formatter((int)$gangdata['gangMONEY'])
        . "
    <br />
    Crystals in vault: {$gangdata['gangCRYSTALS']}
       ";
}

/**
 * @return void
 */
function gang_memberlist(): void
{
    global $db, $userid, $gangdata;
    echo "
    <table cellspacing='1' class='table'>
    		<tr>
    			<th>User</th>
    			<th>Level</th>
    			<th>Days In Gang</th>
    			<th>&nbsp;</th>
    		</tr>
       ";
    $q    = $db->run(
        'SELECT userid, username, daysingang, level FROM users WHERE gang = ? ORDER BY daysingang DESC, level DESC',
        $gangdata['gangID'],
    );
    $csrf = request_csrf_html('yourgang_kickuser');
    foreach ($q as $r) {
        echo "
		<tr>
        	<td><a href='viewuser.php?u={$r['userid']}'>{$r['username']}</a></td>
        	<td>{$r['level']}</td>
        	<td>{$r['daysingang']}</td>
        	<td>
           ";
        if ($gangdata['gangPRESIDENT'] == $userid
            || $gangdata['gangVICEPRES'] == $userid) {
            echo "
            <form action='yourgang.php?action=kick' method='post'>
            	<input type='hidden' name='ID' value='{$r['userid']}' />
            	{$csrf}
            	<input type='submit' value='Kick' />
            </form>";
        } else {
            echo '&nbsp;';
        }
        echo '
			</td>
		</tr>
   		';
    }
    echo "
	</table>
	<br />
	&gt; <a href='yourgang.php'>Go Back</a>
   	";
}

/**
 * @return void
 */
function gang_staff_kick(): void
{
    global $db, $ir, $userid, $gangdata;
    if ($gangdata['gangPRESIDENT'] == $userid
        || $gangdata['gangVICEPRES'] == $userid) {
        csrf_stdverify('yourgang_kickuser', 'members');
        $_POST['ID'] =
            (isset($_POST['ID']) && is_numeric($_POST['ID']))
                ? abs(intval($_POST['ID'])) : 0;
        $who         = $_POST['ID'];
        if ($who == $gangdata['gangPRESIDENT']) {
            echo 'The gang president cannot be kicked.';
        } elseif ($who == $userid) {
            echo 'You cannot kick yourself. If you wish to leave,
            transfer your powers to someone else and then leave like normal.';
        } else {
            $kdata = $db->row(
                'SELECT username FROM users WHERE userid = ? AND gang = ?',
                $who,
                $gangdata['gangID'],
            );
            if (!empty($kdata)) {
                $d_username =
                    htmlentities($kdata['username'], ENT_QUOTES,
                        'ISO-8859-1');
                $save       = function () use ($db, $who, $gangdata, $userid, $ir, $d_username) {
                    $d_oname =
                        htmlentities($ir['username'], ENT_QUOTES, 'ISO-8859-1');
                    $db->update(
                        'users',
                        [
                            'gang' => 0,
                            'daysingang' => 0,
                        ],
                        ['userid' => $who],
                    );
                    $their_event =
                        "You were kicked out of {$gangdata['gangNAME']} by "
                        . "<a href='viewuser.php?u={$userid}'>"
                        . $d_oname . '</a>';
                    event_add($who, $their_event);
                    $gang_event = "<a href='viewuser.php?u={$who}'>"
                        . $d_username
                        . '</a> was kicked out of the gang by '
                        . "<a href='viewuser.php?u={$userid}'>"
                        . $d_oname . '</a>';
                    $db->insert(
                        'gangevents',
                        [
                            'gevGANG' => $gangdata['gangID'],
                            'gevTIME' => time(),
                            'gevTEXT' => $gang_event,
                        ],
                    );
                };
                $db->tryFlatTransaction($save);
                echo "<b>{$d_username}</b> was kicked from the Gang.";
            } else {
                echo 'Trying to kick non-existent user';
            }
        }
    } else {
        echo 'You do not have permission to perform this action.';
    }
}

/**
 * @return void
 */
function gang_forums(): void
{
    global $db, $ir, $gangdata, $domain;
    $r = $db->row(
        'SELECT ff_id, ff_name FROM forum_forums WHERE ff_auth = \'gang\' AND ff_owner = ?',
        $ir['gang'],
    );
    if (empty($r)) {
        $i          = $db->insert(
            'forum_forums',
            [
                'ff_name' => $gangdata['gangNAME'],
                'ff_lp_poster_name' => 'N/A',
                'ff_lp_t_name' => 'N/A',
                'ff_auth' => 'gang',
                'ff_owner' => $gangdata['gangID'],
            ],
        );
        $r          = [];
        $r['ff_id'] = $i;
    } elseif ($r['ff_name'] !== $gangdata['gangNAME']) {
        $db->update(
            'forum_forums',
            ['ff_name' => $gangdata['gangNAME']],
            ['ff_id' => $r['ff_id']],
        );
    }
    ob_get_clean();
    $forum_url = "https://{$domain}/forums.php?viewforum={$r['ff_id']}";
    header("Location: {$forum_url}");
    exit;
}

/**
 * @return void
 */
function gang_donate(): void
{
    global $ir;
    $csrf = request_csrf_html('yourgang_donate');
    echo '
    <b>Enter the amounts you wish to donate.</b>
    <br />
    You have ' . money_formatter($ir['money'])
        . " money and {$ir['crystals']} crystals.
    <br />
    <form action='yourgang.php?action=donate2' method='post'>
    	<table height='300' cellspacing='1' class='table'>
    		<tr>
    			<td>
    				<b>Money:</b><br />
    				<input type='text' name='money' value='0' />
    			</td>
    			<td>
    				<b>Crystals:</b><br />
    				<input type='text' name='crystals' value='0' />
    			</td>
    		</tr>
    		<tr>
    			<td colspan='2' align='center'>
    			    {$csrf}
    				<input type='submit' value='Donate' />
    			</td>
    		</tr>
    	</table>
    </form>
       ";
}

/**
 * @return void
 */
function gang_donate2(): void
{
    global $db, $ir, $userid, $gangdata, $h;
    csrf_stdverify('yourgang_donate', 'donate');
    $_POST['money']    =
        (isset($_POST['money']) && is_numeric($_POST['money']))
            ? abs(intval($_POST['money'])) : 0;
    $_POST['crystals'] =
        (isset($_POST['crystals']) && is_numeric($_POST['crystals']))
            ? abs(intval($_POST['crystals'])) : 0;
    if (empty($_POST['money']) && empty($_POST['crystals'])) {
        echo 'Invalid amount, please go back and try again.<br />
        &gt; <a href="yourgang.php?action=donate">Back</a>';
        $h->endpage();
        exit;
    }
    if ($_POST['money'] > $ir['money']) {
        echo 'You can\'t donate more money than you have,
        	please go back and try again.<br />
        &gt; <a href="yourgang.php?action=donate">Back</a>';
    } elseif ($_POST['crystals'] > $ir['crystals']) {
        echo 'You can\'t donate more crystals than you have,
        	please go back and try again.<br />
        &gt; <a href="yourgang.php?action=donate">Back</a>';
    } else {
        $save = function () use ($db, $userid, $gangdata, $ir) {
            $db->update(
                'users',
                [
                    'money' => new EasyPlaceholder('money - ?', $_POST['money']),
                    'crystals' => new EasyPlaceholder('crystals - ?', $_POST['crystals']),
                ],
                ['userid' => $userid],
            );
            $db->update(
                'gangs',
                [
                    'gangMONEY' => new EasyPlaceholder('gangMONEY + ?', $_POST['money']),
                    'gangCRYSTALS' => new EasyPlaceholder('gangCRYSTALS + ?', $_POST['crystals']),
                ],
                ['gangID' => $gangdata['gangID']],
            );
            $my_name    = htmlentities($ir['username'], ENT_QUOTES, 'ISO-8859-1');
            $gang_event = "<a href='viewuser.php?u={$userid}'>" . $my_name
                . '</a>' . ' donated '
                . money_formatter($_POST['money'])
                . ' and/or '
                . number_format($_POST['crystals'])
                . ' crystals to the Gang.';
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $gangdata['gangID'],
                    'gevTIME' => time(),
                    'gevTEXT' => $gang_event,
                ],
            );
        };
        $db->tryFlatTransaction($save);
        echo 'You donated ' . money_formatter($_POST['money'])
            . " and/or {$_POST['crystals']} crystals to the Gang.<br />
              &gt; <a href='index.php'>Go Home</a>";
    }
}

/**
 * @return void
 */
function gang_leave(): void
{
    global $db, $ir, $userid, $gangdata, $h;
    if ($gangdata['gangPRESIDENT'] == $userid
        || $gangdata['gangVICEPRES'] == $userid) {
        echo "You cannot leave while you are still president
        	or vice-president of your gang.<br />
        &gt; <a href='yourgang.php'>Back</a>";
        $h->endpage();
        exit;
    }
    if (isset($_POST['submit']) && $_POST['submit'] == 'Yes, leave!') {
        csrf_stdverify('yourgang_leave', 'leave');
        $save = function () use ($db, $userid, $ir) {
            $db->update(
                'users',
                [
                    'gang' => 0,
                    'daysingang' => 0,
                ],
                ['userid' => $userid],
            );
            $gang_event = "<a href='viewuser.php?u={$userid}'>"
                . htmlentities($ir['username'], ENT_QUOTES,
                    'ISO-8859-1') . '</a> left the Gang.';
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $ir['gang'],
                    'gevTIME' => time(),
                    'gevTEXT' => $gang_event,
                ],
            );
        };
        $db->tryFlatTransaction($save);
    } elseif (isset($_POST['submit']) && $_POST['submit'] == 'No, stay!') {
        echo "You stayed in your gang.<br />
        &gt; <a href='yourgang.php'>Go back</a>";
    } else {
        $csrf = request_csrf_html('yourgang_leave');
        echo "Are you sure you wish to leave your gang?
        <form action='yourgang.php?action=leave' method='post'>
            {$csrf}
        	<input type='submit' name='submit' value='Yes, leave!' />
        	<br />
        	<br />
        	<input type='submit' name='submit' value='No, stay!'
        	 onclick=\"window.location='yourgang.php';\" />
        </form>";
    }
}

/**
 * @return void
 */
function gang_warview(): void
{
    global $db, $ir, $gangdata;
    $wq = $db->run(
        'SELECT * FROM gangwars WHERE warDECLARER = ? OR warDECLARED = ?',
        $ir['gang'],
        $ir['gang'],
    );
    echo "<b>These are the wars your gang is in.</b><br />
	<table width='75%' cellspacing='1' class='table'>
		<tr>
			<th>Time Started</th>
			<th>Versus</th>
			<th>Who Declared</th>
		</tr>";
    foreach ($wq as $r) {
        if ($gangdata['gangID'] == $r['warDECLARER']) {
            $w = 'You';
            $f = 'warDECLARED';
        } else {
            $w = 'Them';
            $f = 'warDECLARER';
        }
        $d    = date('F j, Y, g:i:s a', (int)$r['warTIME']);
        $them = $db->row(
            'SELECT gangID, gangNAME FROM gangs WHERE gangID = ?',
            $r[$f],
        );
        echo "<tr>
        		<td>$d</td>
        		<td>
        			<a href='gangs.php?action=view&amp;ID={$them['gangID']}'>
                    {$them['gangNAME']}
                    </a>
                </td>
                <td>$w</td>
              </tr>";
    }
    echo '</table>';
}

/**
 * @return void
 */
function gang_atklogs(): void
{
    global $db, $ir;
    $atks = $db->run(
        "SELECT a.*, u1.username AS attackern, u1.gang AS attacker_gang, u2.username AS attackedn, u2.gang AS attacked_gang
        FROM attacklogs AS a
            INNER JOIN users AS u1 ON a.attacker = u1.userid
            INNER JOIN users AS u2 ON a.attacked = u2.userid
        WHERE (u1.gang = ? OR u2.gang = ?) AND result = 'won'
        ORDER BY time DESC
        LIMIT 50",
        $ir['gang'],
        $ir['gang'],
    );
    echo "<b>Attack Logs - The last 50 attacks involving someone in your gang</b><br />
	<table width='75%' cellspacing='1' class='table'>
		<tr>
			<th>Time</th>
			<th>Attack</th>
		</tr>";
    foreach ($atks as $r) {
        if ($r['attacker_gang'] == $ir['gang']) {
            $color = 'green';
        } else {
            $color = 'red';
        }
        $d = date('F j, Y, g:i:s a', (int)$r['time']);
        echo "<tr>
        		<td>$d</td>
        		<td>
        			<a href='viewuser.php?u={$r['attacker']}'>{$r['attackern']}</a>
        			<span style='color: $color;'>attacked</font>
        			<a href='viewuser.php?u={$r['attacked']}'>{$r['attackedn']}</a>
        		</td>
        	  </tr>";
    }
    echo '</table>';
}

/**
 * @return void
 */
function gang_crimes(): void
{
    global $gangdata;
    if ($gangdata['gangCRIME'] > 0) {
        echo "This is the crime your gang is planning at the moment.<br />
		<b>Crime:</b> {$gangdata['ocNAME']}<br />
		<b>Hours Left:</b> {$gangdata['gangCHOURS']}";
    } else {
        echo 'Your gang is not currently planning a crime.';
    }
}

/**
 * @return void
 */
function gang_staff(): void
{
    global $userid, $gangdata, $h;
    if ($gangdata['gangPRESIDENT'] == $userid
        || $gangdata['gangVICEPRES'] == $userid) {
        if (!isset($_GET['act2'])) {
            $_GET['act2'] = 'idx';
        }
        switch ($_GET['act2']) {
            case 'apps':
                gang_staff_apps();
                break;
            case 'vault':
                gang_staff_vault();
                break;
            case 'vicepres':
                gang_staff_vicepres();
                break;
            case 'pres':
                gang_staff_pres();
                break;
            case 'upgrade':
                gang_staff_upgrades();
                break;
            case 'declare':
                gang_staff_wardeclare();
                break;
            case 'surrender':
                gang_staff_surrender();
                break;
            case 'viewsurrenders':
                gang_staff_viewsurrenders();
                break;
            case 'crimes':
                gang_staff_orgcrimes();
                break;
            case 'massmailer':
                gang_staff_massmailer();
                break;
            case 'desc':
                gang_staff_desc();
                break;
            case 'ament':
                gang_staff_ament();
                break;
            case 'name':
                gang_staff_name();
                break;
            case 'tag':
                gang_staff_tag();
                break;
            case 'masspayment':
                gang_staff_masspayment();
                break;
            default:
                gang_staff_idx();
                break;
        }
    } else {
        echo 'Are you lost?<br />
        &gt; <a href="yourgang.php">Go back</a>';
        $h->endpage();
        exit;
    }
}

/**
 * @return void
 */
function gang_staff_idx(): void
{
    global $userid, $gangdata;
    echo "
    <b>General</b>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=vault'>Vault Management</a>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=apps'>Application Management</a>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=vicepres'>Change Vice-President</a>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=upgrade'>Upgrade Gang</a>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=crimes'>Organised Crimes</a>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=masspayment'>Mass Payment</a>
    <br />
    <a href='yourgang.php?action=staff&amp;act2=ament'>Change Gang Announcement</a>
    <br />
       ";
    if ($gangdata['gangPRESIDENT'] == $userid) {
        echo "
        <hr />
        <a href='yourgang.php?action=staff&amp;act2=pres'>Change President</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=declare'>Declare War</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=surrender'>Surrender</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=viewsurrenders'>View or Accept Surrenders</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=massmailer'>Mass Mail Gang</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=name'>Change Gang Name</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=desc'>Change Gang Desc.</a>
        <br />
        <a href='yourgang.php?action=staff&amp;act2=tag'>Change Gang Tag</a>
           ";
    }
}

/**
 * @return void
 */
function gang_staff_apps(): void
{
    global $db, $ir, $userid, $gangdata, $h;
    $_POST['app'] =
        (isset($_POST['app']) && is_numeric($_POST['app']))
            ? abs(intval($_POST['app'])) : '';
    $what         =
        (isset($_POST['what'])
            && in_array($_POST['what'], ['accept', 'decline'],
                true)) ? $_POST['what'] : '';
    if (!empty($_POST['app']) && !empty($what)) {
        csrf_stdverify('yourgang_staff_apps', 'staff&amp;act2=apps');
        $appdata = $db->row(
            'SELECT appUSER, username
            FROM applications AS a
            INNER JOIN users AS u ON a.appUSER = u.userid
            WHERE a.appID = ? AND a.appGANG = ?',
            $_POST['app'],
            $gangdata['gangID'],
        );
        if (!empty($appdata)) {
            if ($what == 'decline') {
                $save = function () use ($db, $appdata, $gangdata, $userid, $ir) {
                    $db->delete(
                        'applications',
                        ['appID' => $_POST['app']],
                    );
                    event_add($appdata['appUSER'],
                        "Your application to join the {$gangdata['gangNAME']} gang was declined");
                    $gang_event = "<a href='viewuser.php?u={$userid}'>"
                        . $ir['username']
                        . '</a> has declined '
                        . "<a href='viewuser.php?u={$appdata['appUSER']}'>"
                        . $appdata['username']
                        . '</a>\'s application to join the Gang.';
                    $db->insert(
                        'gangevents',
                        [
                            'gevGANG' => $gangdata['gangID'],
                            'gevTIME' => time(),
                            'gevTEXT' => $gang_event,
                        ],
                    );
                };
                $db->tryFlatTransaction($save);
                echo "
                You have declined the application by {$appdata['username']}.
                <br />
                <a href='yourgang.php?action=staff&amp;act2=apps'>&gt; Back</a>
                   ";
            } else {
                $cnt = $db->cell(
                    'SELECT COUNT(userid) FROM users WHERE gang = ?',
                    $gangdata['gangID']
                );
                if ($gangdata['gangCAPACITY'] <= $cnt) {
                    echo 'Your gang is full, you must upgrade it to hold more before you can accept another user!';
                    $h->endpage();
                    exit;
                } elseif ($appdata['gang'] != 0) {
                    echo 'That person is already in a gang.';
                    $h->endpage();
                    exit;
                }
                $save = function () use ($db, $appdata, $gangdata, $userid, $ir) {
                    $db->delete(
                        'applications',
                        ['appID' => $_POST['app']],
                    );
                    event_add($appdata['appUSER'],
                        "Your application to join the {$gangdata['gangNAME']} gang was accepted, Congrats!");
                    $gang_event = "<a href='viewuser.php?u={$userid}'>"
                        . $ir['username']
                        . '</a> has accepted '
                        . "<a href='viewuser.php?u={$appdata['appUSER']}'>"
                        . $appdata['username']
                        . '</a>\'s application to join the Gang.';
                    $db->insert(
                        'gangevents',
                        [
                            'gevGANG' => $gangdata['gangID'],
                            'gevTIME' => time(),
                            'gevTEXT' => $gang_event,
                        ],
                    );
                    $db->update(
                        'users',
                        [
                            'gang' => $gangdata['gangID'],
                            'daysingang' => 0,
                        ],
                        ['userid' => $appdata['appUSER']],
                    );
                };
                $db->tryFlatTransaction($save);
                echo "
                You have accepted the application by {$appdata['username']}.
                <br />
                &gt; <a href='yourgang.php?action=staff&amp;act2=apps'>Back</a>
                   ";
            }
        } else {
            echo "Invalid application.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=apps'>Back</a>";
        }
    } else {
        echo "
        <b>Applications</b>
        <br />
        <table width='85%' cellspacing='1' class='table'>
        		<tr>
        			<th>User</th>
        			<th>Level</th>
        			<th>Money</th>
        			<th>Reason</th>
        			<th>&nbsp;</th>
        		</tr>
   		";
        $q    = $db->run(
            'SELECT appTEXT, userid, username, level, money, appID
            FROM applications AS a
            INNER JOIN users AS u ON a.appUSER = u.userid
            WHERE a.appGANG = ?',
            $gangdata['gangID'],
        );
        $csrf = request_csrf_html('yourgang_staff_apps');
        foreach ($q as $r) {
            $r['appTEXT'] =
                htmlentities($r['appTEXT'], ENT_QUOTES, 'ISO-8859-1',
                    false);
            echo "
            <tr>
            	<td>
            		<a href='viewuser.php?u={$r['userid']}'>{$r['username']}</a>
            		[{$r['userid']}]
            	</td>
            	<td>{$r['level']}</td>
            	<td>" . money_formatter((int)$r['money'])
                . "</td>
            	<td>{$r['appTEXT']}</td>
            	<td>
            		<form action='yourgang.php?action=staff&amp;act2=apps' method='post'>
            			<input type='hidden' name='app' value='{$r['appID']}' />
            			<input type='hidden' name='what' value='accept' />
            			{$csrf}
            			<input type='submit' value='Accept' />
            		</form>
            		<form action='yourgang.php?action=staff&amp;act2=apps' method='post'>
            			<input type='hidden' name='app' value='{$r['appID']}' />
            			<input type='hidden' name='what' value='decline' />
            			{$csrf}
            			<input type='submit' value='Decline' />
            		</form>
            	</td>
            </tr>
               ";
        }
        echo '</table>';
    }
}

/**
 * @return void
 */
function gang_staff_vault(): void
{
    global $db, $gangdata, $h;
    $_POST['who'] =
        (isset($_POST['who']) && is_numeric($_POST['who']))
            ? abs(intval($_POST['who'])) : '';
    if (!empty($_POST['who'])) {
        csrf_stdverify('yourgang_staff_vault', 'staff&amp;act2=vault');
        $_POST['crystals'] =
            (isset($_POST['crystals']) && is_numeric($_POST['crystals']))
                ? abs(intval($_POST['crystals'])) : 0;
        $_POST['money']    =
            (isset($_POST['money']) && is_numeric($_POST['money']))
                ? abs(intval($_POST['money'])) : 0;
        if ($_POST['crystals'] > $gangdata['gangCRYSTALS']) {
            echo 'The vault does not have that many crystals!';
        } elseif ($_POST['money'] > $gangdata['gangMONEY']) {
            echo 'The vault does not have that much money!';
        } elseif ($_POST['money'] == 0 && $_POST['crystals'] == 0) {
            echo 'You cannot give nothing away.';
        } else {
            $who   = $_POST['who'];
            $dname = $db->cell(
                "SELECT username FROM users WHERE userid = $who AND gang = ?",
                $who,
                $gangdata['gangID'],
            );
            if (empty($dname)) {
                echo "That user doesn't exist or isn't in this gang.<br />
                &gt; <a href='yourgang.php?action=staff&amp;act2=vault'>Back</a>";
                $h->endpage();
                exit;
            }
            $dname = htmlentities($dname, ENT_QUOTES, 'ISO-8859-1');
            $money = $_POST['money'];
            $crys  = $_POST['crystals'];
            $save  = function () use ($db, $money, $crys, $who, $gangdata, $dname) {
                $db->update(
                    'users',
                    [
                        'money' => new EasyPlaceholder('money + ?', $money),
                        'crystals' => new EasyPlaceholder('crystals + ?', $crys),
                    ],
                    ['userid' => $who],
                );
                $db->update(
                    'gangs',
                    [
                        'gangMONEY' => new EasyPlaceholder('gangMONEY - ?', $money),
                        'gangCRYSTALS' => new EasyPlaceholder('gangCRYSTALS - ?', $crys),
                    ],
                    ['gangID' => $gangdata['gangID']],
                );
                event_add($who,
                    'You were given ' . money_formatter($money)
                    . " and/or $crys crystals from your Gang.");
                $gang_event = "<a href='viewuser.php?u=$who'>" . $dname
                    . '</a> was given '
                    . money_formatter($money) . ' and/or '
                    . number_format($crys)
                    . ' crystals from the Gang.';
                $db->insert(
                    'gangevents',
                    [
                        'gevGANG' => $gangdata['gangID'],
                        'gevTIME' => time(),
                        'gevTEXT' => $gang_event,
                    ],
                );
            };
            $db->tryFlatTransaction($save);
            echo "<a href='viewuser.php?u=$who'>{$dname}</a> was given "
                . money_formatter($money) . ' and/or '
                . number_format($crys) . ' crystals from the Gang.';
        }
    } else {
        $csrf = request_csrf_html('yourgang_staff_vault');
        echo 'The vault has ' . money_formatter((int)$gangdata['gangMONEY'])
            . " and {$gangdata['gangCRYSTALS']} crystals.<br />
        <form action='yourgang.php?action=staff&amp;act2=vault' method='post'>
        Give
        	\$<input type='text' name='money' /> and
        	<input type='text' name='crystals' /> crystals
        <br />
        To: <select name='who' type='dropdown'>";
        $q = $db->run(
            'SELECT userid , username FROM users WHERE gang = ?',
            $gangdata['gangID'],
        );
        foreach ($q as $r) {
            echo "\n<option value='{$r['userid']}'>{$r['username']}</option>";
        }
        echo "</select><br />
        {$csrf}
		<input type='submit' value='Give' /></form>";
    }
}

/**
 * @return void
 */
function gang_staff_vicepres(): void
{
    global $db, $gangdata, $h;
    if (isset($_POST['subm'])) {
        csrf_stdverify('gang_staff_vicepres', 'staff&amp;act2=vicepres');
        $_POST['vp'] =
            (isset($_POST['vp']) && is_numeric($_POST['vp']))
                ? abs(intval($_POST['vp'])) : 0;
        $memb        = $db->row(
            'SELECT userid, username FROM users WHERE userid = ? AND gang = ?',
            $_POST['vp'],
            $gangdata['gangID'],
        );
        if (empty($memb)) {
            echo "Invalid user or user not in your gang.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=vicepres'>Back</a>";
            $h->endpage();
            exit;
        }
        $save = function () use ($db, $gangdata, $memb) {
            $db->update(
                'gangs',
                ['gangVICEPRES' => $_POST['vp']],
                ['gangID' => $gangdata['gangID']],
            );
            event_add($memb['userid'],
                "You were transferred vice-presidency of {$gangdata['gangNAME']}.");
        };
        $db->tryFlatTransaction($save);
        $m_name = htmlentities($memb['username'], ENT_QUOTES, 'ISO-8859-1');
        echo "Vice-Presidency was transferred to {$m_name}";
    } else {
        $csrf = request_csrf_html('gang_staff_vicepres');
        $vp   = $gangdata['gangVICEPRES'];
        echo "
        <form action='yourgang.php?action=staff&amp;act2=vicepres' method='post'>
			Enter the ID of the new vice-president.<br />
			<input type='hidden' name='subm' value='submit' />
			{$csrf}
			ID: <input type='text' name='vp' value='{$vp}' maxlength='7' size='7' /><br />
			<input type='submit' value='Change' />
		</form>";
    }
}

/**
 * @return void
 */
function gang_staff_wardeclare(): void
{
    global $db, $gangdata, $h;
    if (isset($_POST['subm'])) {
        csrf_stdverify('yourgang_staff_declare', 'staff&amp;act2=declare');
        $_POST['gang'] =
            (isset($_POST['gang']) && is_numeric($_POST['gang']))
                ? abs(intval($_POST['gang'])) : 0;
        if ($_POST['gang'] == $gangdata['gangID']) {
            echo "You can't declare war on your own gang.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=declare'>Go back</a>";
            $h->endpage();
            exit;
        }
        // Check for existence
        $them = $db->cell(
            'SELECT gangNAME FROM gangs WHERE gangID = ?',
            $_POST['gang'],
        );
        if (empty($them)) {
            echo "Invalid gang to declare on.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=declare'>Go back</a>";
            $h->endpage();
            exit;
        }
        $save = function () use ($db, $gangdata, $them) {
            $db->insert(
                'gangwars',
                [
                    'warDECLARER' => $gangdata['gangID'],
                    'warDECLARED' => $_POST['gang'],
                    'warTIME' => time(),
                ],
            );
            $event   = "<a href='gangs.php?action=view&amp;ID={$gangdata['gangID']}'>"
                . $gangdata['gangNAME']
                . '</a> declared war on '
                . "<a href='gangs.php?action=view&amp;ID={$_POST['gang']}'>"
                . $them . '</a>';
            $ev_time = time();
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $gangdata['gangID'],
                    'gevTIME' => $ev_time,
                    'gevTEXT' => $event,
                ],
            );
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $_POST['gang'],
                    'gevTIME' => $ev_time,
                    'gevTEXT' => $event,
                ],
            );
        };
        $db->tryFlatTransaction($save);
        echo 'You have declared war!';
    } else {
        $csrf = request_csrf_html('yourgang_staff_declare');
        echo "
        <form action='yourgang.php?action=staff&amp;act2=declare' method='post'>
			Choose who to declare war on.<br />
			<input type='hidden' name='subm' value='submit' />
			Gang: <select name='gang' type='dropdown'>";
        $q = $db->run(
            'SELECT gangID, gangNAME FROM gangs WHERE gangID != ?',
            $gangdata['gangID']
        );
        foreach ($q as $r) {
            echo "<option value='{$r['gangID']}'>{$r['gangNAME']}</option>\n";
        }
        echo "</select><br />
        	{$csrf}
			<input type='submit' value='Declare' />
		</form>";
    }
}

/**
 * @return void
 */
function gang_staff_surrender(): void
{
    global $db, $gangdata, $h;
    if (!isset($_POST['subm'])) {
        $wars = $db->run(
            'SELECT * FROM gangwars WHERE ? IN (warDECLARER, warDECLARED)',
            $gangdata['gangID'],
        );
        if (!empty($wars)) {
            $csrf = request_csrf_html('yourgang_staff_surrender');
            echo "
        	<form action='yourgang.php?action=staff&amp;act2=surrender' method='post'>
				Choose who to surrender to.<br />
				<input type='hidden' name='subm' value='submit' />
				Gang: <select name='war' type='dropdown'>\n";
            foreach ($wars as $r) {
                if ($gangdata['gangID'] == $r['warDECLARER']) {
                    $f = 'warDECLARED';
                } else {
                    $f = 'warDECLARER';
                }
                $them = $db->cell(
                    'SELECT gangNAME FROM gangs WHERE gangID = ?',
                    $r[$f],
                );
                echo "<option value='{$r['warID']}'>{$them}</option>\n";
            }
            echo "</select><br />
				Message: <input type='text' name='msg' /><br />
				{$csrf}
				<input type='submit' value='Surrender' />
			</form>";
        } else {
            echo "You aren't in any wars!";
        }
    } else {
        csrf_stdverify('yourgang_staff_surrender', 'staff&amp;act2=surrender');
        $_POST['war'] =
            (isset($_POST['war']) && is_numeric($_POST['war']))
                ? abs(intval($_POST['war'])) : 0;
        $e_msg        = htmlentities(stripslashes($_POST['msg']), ENT_QUOTES, 'ISO-8859-1');
        $r            = $db->row(
            'SELECT * FROM gangwars WHERE warID = ?',
            $_POST['war'],
        );
        if (empty($r)) {
            echo "Invalid war.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=surrender'>Back</a>";
            $h->endpage();
            exit;
        }
        if ($gangdata['gangID'] == $r['warDECLARER']) {
            $f = 'warDECLARED';
        } elseif ($gangdata['gangID'] == $r['warDECLARED']) {
            $f = 'warDECLARER';
        } else {
            echo "Invalid war.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=surrender'>Back</a>";
            $h->endpage();
            exit;
        }
        $them = $db->cell(
            'SELECT gangNAME FROM gangs WHERE gangID = ?',
            $r[$f],
        );
        $save = function () use ($db, $gangdata, $r, $them, $f, $e_msg) {
            $db->insert(
                'surrenders',
                [
                    'surWAR' => $_POST['war'],
                    'surWHO' => $gangdata['gangID'],
                    'surTO' => $r[$f],
                    'surMSG' => $e_msg,
                ],
            );
            $event  =
                "<a href='gangs.php?action=view&amp;ID={$gangdata['gangID']}'>"
                . $gangdata['gangNAME']
                . '</a> have asked to surrender the war against '
                . "<a href='gangs.php?action=view&amp;ID={$r[$f]}'>"
                . $them . '</a>';
            $e_time = time();
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $gangdata['gangID'],
                    'gevTIME' => $e_time,
                    'gevTEXT' => $event,
                ],
            );
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $r[$f],
                    'gevTIME' => $e_time,
                    'gevTEXT' => $event,
                ],
            );
        };
        $db->tryFlatTransaction($save);
        echo 'You have asked to surrender.';
    }
}

/**
 * @return void
 */
function gang_staff_viewsurrenders(): void
{
    global $db, $gangdata, $h;
    if (!isset($_POST['subm'])) {
        $wq = $db->run(
            'SELECT surID, surMSG, w.*
            FROM surrenders AS s
            INNER JOIN gangwars AS w ON s.surWAR = w.warID
            WHERE surTO = ?',
            $gangdata['gangID'],
        );
        if (!empty($wq)) {
            $csrf = request_csrf_html('yourgang_staff_acceptsurrender');
            echo "
        	<form action='yourgang.php?action=staff&amp;act2=viewsurrenders' method='post'>
				Choose who to accept the surrender from.<br />
				<input type='hidden' name='subm' value='submit' />
				Gang: <select name='sur' type='dropdown'>";
            foreach ($wq as $r) {
                if ($gangdata['gangID'] == $r['warDECLARER']) {
                    $f = 'warDECLARED';
                } else {
                    $f = 'warDECLARER';
                }
                $them = $db->cell(
                    'SELECT gangNAME FROM gangs WHERE gangID = ?',
                    $r[$f],
                );
                echo "<option value='{$r['surID']}'>War vs. {$them} (Msg: {$r['surMSG']})</option>";
            }
            echo "</select><br />
                {$csrf}
            	<input type='submit' value='Accept Surrender' />
            </form>";
        } else {
            echo 'There are no active surrenders for you to deal with.';
        }
    } else {
        csrf_stdverify('yourgang_staff_acceptsurrender',
            'staff&amp;act2=viewsurrenders');
        $_POST['sur'] =
            (isset($_POST['sur']) && is_numeric($_POST['sur']))
                ? abs(intval($_POST['sur'])) : 0;
        $surr         = $db->row(
            'SELECT w.*
            FROM surrenders AS s
            INNER JOIN gangwars AS w ON s.surWAR = w.warID
            WHERE surID = ? AND surTO = ?',
            $_POST['sur'],
            $gangdata['gangID'],
        );
        if (empty($surr)) {
            echo "Invalid surrender.<br />
            &gt; <a href='yourgang.php?action=staff&amp;act2=viewsurrenders'>Back</a>";
            $h->endpage();
            exit;
        }
        $f    = $gangdata['gangID'] == $surr['warDECLARER'] ? 'warDECLARED' : 'warDECLARER';
        $them = $db->cell(
            'SELECT gangNAME FROM gangs WHERE gangID = ?',
            $surr[$f],
        );
        $save = function () use ($db, $gangdata, $surr, $f, $them) {
            $warID     = $surr['warID'];
            $ids       = [$surr['warDECLARER'], $surr[$f]];
            $statement = EasyStatement::open()
                ->in('surWHO IN (?*)', $ids)
                ->orIn('surTO IN (?*)', $ids);
            $db->safeQuery(
                'DELETE FROM surrenders WHERE ' . $statement,
                $statement->values(),
            );
            $db->delete(
                'gangwars',
                ['warID' => $warID],
            );
            $event   =
                "<a href='gangs.php?action=view&amp;ID={$gangdata['gangID']}'>"
                . $gangdata['gangNAME']
                . '</a> have accepted the surrender from '
                . "<a href='gangs.php?action=view&amp;ID={$surr[$f]}'>"
                . $them . '</a>, the war is over!';
            $ev_time = time();
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $gangdata['gangID'],
                    'gevTIME' => $ev_time,
                    'gevTEXT' => $event,
                ],
            );
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $surr[$f],
                    'gevTIME' => $ev_time,
                    'gevTEXT' => $event,
                ],
            );
        };
        $db->tryFlatTransaction($save);
        echo "You have accepted the surrender from {$them}, the war is over.";
    }
}

/**
 * @return void
 */
function gang_staff_orgcrimes(): void
{
    global $db, $gangdata, $h;
    $_POST['crime'] =
        (isset($_POST['crime']) && is_numeric($_POST['crime']))
            ? abs(intval($_POST['crime'])) : 0;
    if ($_POST['crime']) {
        csrf_stdverify('yourgang_staff_orgcrimes', 'staff&amp;act2=crimes');
        if ($gangdata['gangCRIME'] != 0) {
            echo 'Your gang is already doing a crime!';
        } else {
            // Check Existence
            $crime_exists = $db->exists(
                'SELECT COUNT(ocID) FROM orgcrimes WHERE ocID = ?',
                $_POST['crime'],
            );
            if (!$crime_exists) {
                echo "Invalid crime.<br />
            	&gt; <a href='yourgang.php?action=staff&amp;act2=crimes'>Back</a>";
                $h->endpage();
                exit;
            }
            $db->update(
                'gangs',
                [
                    'gangCRIME' => $_POST['crime'],
                    'gangCHOURS' => 24,
                ],
                ['gangID' => $gangdata['gangID']],
            );
            echo 'You have started to plan this crime. It will take 24 hours.';
        }
    } else {
        $membs = $db->cell(
            'SELECT COUNT(userid) FROM users WHERE gang = ?',
            $gangdata['gangID'],
        );
        $q     = $db->run(
            'SELECT ocID, ocNAME, ocUSERS FROM orgcrimes WHERE ocUSERS <= ?',
            $membs,
        );
        if (!empty($q)) {
            $csrf = request_csrf_html('yourgang_staff_orgcrimes');
            echo "<h3>Organised Crimes</h3>
			<form action='yourgang.php?action=staff&amp;act2=crimes' method='post'>
				Choose a crime that your gang should commit.<br />
				<select name='crime' type='dropdown'>";
            foreach ($q as $r) {
                echo "<option value='{$r['ocID']}'>{$r['ocNAME']}
                		({$r['ocUSERS']} members needed)</option>\n";
            }
            echo "</select>
            	<br />
            	{$csrf}
            	<input type='submit' value='Commit' />
            </form>";
        } else {
            echo '<h3>Organised Crimes</h3>
            There are no crimes that your gang can do.';
        }
    }
}

/**
 * @return void
 */
function gang_staff_pres(): void
{
    global $db, $userid, $gangdata, $h;
    if ($gangdata['gangPRESIDENT'] == $userid) {
        if (isset($_POST['subm'])) {
            csrf_stdverify('yourgang_staff_president', 'staff&amp;act2=pres');
            $_POST['pres'] =
                (isset($_POST['pres']) && is_numeric($_POST['pres']))
                    ? abs(intval($_POST['pres'])) : 0;
            $memb          = $db->row(
                'SELECT userid, username FROM users WHERE userid = ? AND gang = ?',
                $_POST['pres'],
                $gangdata['gangID'],
            );
            if (empty($memb)) {
                echo "Invalid user or user not in your gang.<br />
            	&gt; <a href='yourgang.php?action=staff&amp;act2=pres'>Back</a>";
                $h->endpage();
                exit;
            }
            $save = function () use ($db, $gangdata, $memb) {
                $db->update(
                    'gangs',
                    ['gangPRESIDENT' => $_POST['pres']],
                    ['gangID' => $gangdata['gangID']],
                );
                event_add($memb['userid'],
                    "You were transferred presidency of {$gangdata['gangNAME']}.");
            };
            $db->tryFlatTransaction($save);
            echo "Presidency was transferred to {$memb['username']}<br />
            &gt; <a href='yourgang.php'>Gang home</a>";
        } else {
            $currp = $gangdata['gangPRESIDENT'];
            $csrf  = request_csrf_html('yourgang_staff_president');
            echo "
            <form action='yourgang.php?action=staff&amp;act2=pres' method='post'>
				Enter the ID of the new president.<br />
				<input type='hidden' name='subm' value='submit' />
				ID: <input type='text' name='pres' value='{$currp}' maxlength='7' size='7' /><br />
				{$csrf}
				<input type='submit' value='Change' />
			</form>";
        }
    } else {
        echo 'This action is only available to the president of the gang.';
    }
}

/**
 * @return void
 */
function gang_staff_upgrades(): void
{
    global $db, $gangdata;
    if (isset($_POST['membs'])) {
        csrf_stdverify('yourgang_staff_capacity', 'staff&amp;act2=upgrade');
        $_POST['membs'] =
            (is_numeric($_POST['membs']))
                ? abs(intval($_POST['membs'])) : 0;
        if ($_POST['membs'] == 0) {
            echo "There's no point upgrading 0 capacity.";
        } elseif ($_POST['membs'] * 100000 > $gangdata['gangMONEY']) {
            echo 'Your gang does not have enough money to upgrade that much capacity.';
        } else {
            $cost = $_POST['membs'] * 100000;
            $db->update(
                'gangs',
                [
                    'gangCAPACITY' => new EasyPlaceholder('gangCAPACITY + ?', $_POST['membs']),
                    'gangMONEY' => new EasyPlaceholder('gangMONEY - ?', $cost),
                ],
                ['gangID' => $gangdata['gangID']],
            );
            echo 'You paid ' . money_formatter($cost)
                . " to add {$_POST['membs']} capacity to your gang.";
        }
    } else {
        $csrf = request_csrf_html('yourgang_staff_capacity');
        echo "<h3>Capacity</h3>
		Current Capacity: {$gangdata['gangCAPACITY']}<br />
		<form action='yourgang.php?action=staff&amp;act2=upgrade' method='post'>
			Enter the amount of extra capacity you need.
			Each extra member slot costs " . money_formatter(100000)
            . ".<br />
			<input type='text' name='membs' value='1' /><br />
			{$csrf}
			<input type='submit' value='Buy' />
		</form>";
    }
}

/**
 * @return void
 */
function gang_staff_massmailer(): void
{
    global $db, $ir, $gangdata;
    $_POST['text'] =
        (isset($_POST['text']) && strlen($_POST['text']) < 500)
            ? htmlentities(stripslashes($_POST['text']), ENT_QUOTES, 'ISO-8859-1') : '';
    if (!empty($_POST['text'])) {
        csrf_stdverify('yourgang_staff_massmailer',
            'staff&amp;act2=massmailer');
        $q    = $db->run(
            'SELECT username, userid FROM users WHERE gang = ?',
            $gangdata['gangID']
        );
        $save = function () use ($db, $q, $ir) {
            $subj      = 'This is a mass mail from your gang';
            $mass_time = time();
            foreach ($q as $r) {
                $db->insert(
                    'mail',
                    [
                        'mail_from' => $ir['userid'],
                        'mail_to' => $r['userid'],
                        'mail_time' => $mass_time,
                        'mail_subject' => $subj,
                        'mail_text' => $_POST['text'],
                    ],
                );
                echo "Mass mail sent to {$r['username']}.<br />";
            }
        };
        $db->tryFlatTransaction($save);
        echo "
		Mass mail sending complete!
		<br />
		&gt; <a href='yourgang.php?action=staff'>Go Back</a>
   		";
    } else {
        $csrf = request_csrf_html('yourgang_staff_massmailer');
        echo "
        <h3>Mass Mailer</h3>
        <form action='yourgang.php?action=staff&amp;act2=massmailer' method='post'>
        	Text: <br />
        	<textarea name='text' rows='7' cols='40'></textarea>
        	<br />
        	{$csrf}
        	<input type='submit' value='Send' />
        </form>
           ";
    }
}

/**
 * @return void
 */
function gang_staff_masspayment(): void
{
    global $db, $gangdata;
    $_POST['amt'] =
        (isset($_POST['amt']) && is_numeric($_POST['amt']))
            ? abs(intval($_POST['amt'])) : 0;
    if ($_POST['amt']) {
        csrf_stdverify('yourgang_staff_masspayment',
            'staff&amp;act2=masspayment');
        $q    = $db->run(
            'SELECT userid, username FROM users WHERE gang = ?',
            $gangdata['gangID'],
        );
        $save = function () use ($db, $gangdata, $q) {
            foreach ($q as $r) {
                if ($gangdata['gangMONEY'] >= $_POST['amt']) {
                    event_add($r['userid'],
                        'You were given ' . money_formatter($_POST['amt'])
                        . ' from your gang.');
                    $db->update(
                        'users',
                        ['money' => new EasyPlaceholder('money + ?', $_POST['amt'])],
                        ['userid' => $r['userid']],
                    );
                    $gangdata['gangMONEY'] -= $_POST['amt'];
                    echo "Money sent to {$r['username']}.<br />";
                } else {
                    echo "Not enough in the vault to pay {$r['username']}!<br />";
                }
            }
            $db->update(
                'gangs',
                ['gangMONEY' => $gangdata['gangMONEY']],
                ['gangID' => $gangdata['gangID']],
            );
            $credit_evt = 'A mass payment of ' . money_formatter($_POST['amt']) . ' was sent to the members of the Gang.';
            $db->insert(
                'gangevents',
                [
                    'gevGANG' => $gangdata['gangID'],
                    'gevTIME' => time(),
                    'gevTEXT' => $credit_evt,
                ],
            );
        };
        $db->tryFlatTransaction($save);
        echo "Mass payment sending complete!<br />
		&gt; <a href='yourgang.php?action=staff'>Back</a>";
    } else {
        $csrf = request_csrf_html('yourgang_staff_masspayment');
        echo "<h3>Mass Payment</h3>
		<form action='yourgang.php?action=staff&amp;act2=masspayment' method='post'>
			Amount: <input type='text' name='amt' value='0' /><br />
			{$csrf}
			<input type='submit' value='Send' />
		</form>";
    }
}

/**
 * @return void
 */
function gang_staff_desc(): void
{
    global $db, $userid, $gangdata;
    if ($gangdata['gangPRESIDENT'] == $userid) {
        if (isset($_POST['subm']) && isset($_POST['desc'])) {
            csrf_stdverify('yourgang_staff_desc', 'staff&amp;act2=desc');
            $desc = nl2br(
                htmlentities(
                    stripslashes($_POST['desc']),
                    ENT_QUOTES, 'ISO-8859-1'));
            $db->update(
                'gangs',
                ['gangDESC' => $desc],
                ['gangID' => $gangdata['gangID']],
            );
            echo "Gang description changed!<br />
			&gt; <a href='yourgang.php?action=staff'>Back</a>";
        } else {
            $desc_for_area = strip_tags($gangdata['gangDESC']);
            $csrf          = request_csrf_html('yourgang_staff_desc');
            echo "Current Description: <br />
            {$gangdata['gangDESC']}
            <form action='yourgang.php?action=staff&amp;act2=desc' method='post'>
				Enter the new description.<br />
				<input type='hidden' name='subm' value='submit' />
				Desc: <br />
				<textarea name='desc' cols='40' rows='7'>{$desc_for_area}</textarea><br />
				{$csrf}
				<input type='submit' value='Change' />
			</form>";
        }
    } else {
        echo 'This action is only available to the president of the gang.';
    }
}

/**
 * @return void
 */
function gang_staff_ament(): void
{
    global $db, $userid, $gangdata;
    if ($gangdata['gangPRESIDENT'] == $userid) {
        if (isset($_POST['subm']) && isset($_POST['ament'])) {
            csrf_stdverify('yourgang_staff_ament', 'staff&amp;act2=ament');
            $ament = nl2br(
                htmlentities(
                    stripslashes($_POST['ament']),
                    ENT_QUOTES, 'ISO-8859-1'));
            $db->update(
                'gangs',
                ['gangAMENT' => $ament],
                ['gangID' => $gangdata['gangID']],
            );
            echo "Gang announcement changed!<br />
			&gt; <a href='yourgang.php?action=staff'>Back</a>";
        } else {
            $am_for_area = strip_tags($gangdata['gangAMENT']);
            $csrf        = request_csrf_html('yourgang_staff_ament');
            echo "Current Announcement: <br />
            {$gangdata['gangAMENT']}
            <form action='yourgang.php?action=staff&amp;act2=ament' method='post'>
				Enter the new announcement.<br />
				<input type='hidden' name='subm' value='submit' />
				Announcement: <br />
				<textarea name='ament' cols='40' rows='7'>{$am_for_area}</textarea><br />
				{$csrf}
				<input type='submit' value='Change' />
			</form>";
        }
    } else {
        echo 'This action is only available to the president of the gang.';
    }
}

/**
 * @return void
 */
function gang_staff_name(): void
{
    global $db, $userid, $gangdata;
    if ($gangdata['gangPRESIDENT'] == $userid) {
        if (isset($_POST['subm']) && isset($_POST['name'])) {
            csrf_stdverify('yourgang_staff_name', 'staff&amp;act2=name');
            $name = htmlentities(stripslashes($_POST['name']), ENT_QUOTES, 'ISO-8859-1');
            $db->update(
                'gangs',
                ['gangNAME' => $name],
                ['gangID' => $gangdata['gangID']],
            );
            echo "Gang name changed!<br />
			&gt; <a href='yourgang.php?action=staff'>Back</a>";
        } else {
            $csrf  = request_csrf_html('yourgang_staff_name');
            $gname = $gangdata['gangNAME'];
            echo "
            <form action='yourgang.php?action=staff&amp;act2=name' method='post'>
				Enter the new gang name.<br />
				<input type='hidden' name='subm' value='submit' />
				Name: <input type='text' name='name' value='{$gname}' /><br />
				{$csrf}
				<input type='submit' value='Change' />
			</form>";
        }
    } else {
        echo 'This action is only available to the president of the gang.';
    }
}

/**
 * @return void
 */
function gang_staff_tag(): void
{
    global $db, $userid, $gangdata;
    if ($gangdata['gangPRESIDENT'] == $userid) {
        if (isset($_POST['subm']) && isset($_POST['tag'])) {
            csrf_stdverify('yourgang_staff_tag', 'staff&amp;act2=tag');
            $tag = htmlentities(stripslashes($_POST['tag']), ENT_QUOTES, 'ISO-8859-1');
            $db->update(
                'gangs',
                ['gangPREF' => $tag],
                ['gangID' => $gangdata['gangID']],
            );
            echo "Gang tag changed!<br />
			&gt; <a href='yourgang.php?action=staff'>Back</a>";
        } else {
            $csrf = request_csrf_html('yourgang_staff_tag');
            $gtag = $gangdata['gangPREF'];
            echo "
            <form action='yourgang.php?action=staff&amp;act2=tag' method='post'>
				Enter the new gang tag.<br />
				<input type='hidden' name='subm' value='submit' />
				Tag: <input type='text' name='tag' value='{$gtag}' /><br />
				{$csrf}
				<input type='submit' value='Change' />
			</form>";
        }
    } else {
        echo 'This action is only available to the president of the gang.';
    }
}

$h->endpage();
