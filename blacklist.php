<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

global $ir, $userid, $h;
require_once('globals.php');
if ($ir['donatordays'] == 0) {
    echo 'This feature is for donators only.';
    $h->endpage();
    exit;
}
echo '<h3>Black List</h3>';
if (!isset($_GET['action'])) {
    $_GET['action'] = '';
}
switch ($_GET['action']) {
    case 'add':
        add_enemy();
        break;
    case 'remove':
        remove_enemy();
        break;
    case 'ccomment':
        change_comment();
        break;
    default:
        black_list();
        break;
}

/**
 * @return void
 */
function black_list(): void
{
    global $db, $ir, $userid;
    echo "
<a href='blacklist.php?action=add'>&gt; Add an Enemy</a><br />
These are the people on your black list.
<br />
    {$ir['enemy_count']} people have added you to their list.
<br />
Most hated: [";
    $q2r = $db->run(
        'SELECT username, userid FROM users ORDER BY enemy_count DESC LIMIT 5'
    );
    $r   = 0;
    foreach ($q2r as $r2r) {
        $r++;
        if ($r > 1) {
            echo ' | ';
        }
        echo "<a href='viewuser.php?u={$r2r['userid']}'>{$r2r['username']}</a>";
    }
    echo ']
<table width="90%" class="table" cellspacing="1">
		<tr style="background:gray">
	<th>ID</th>
	<th>Name</th>
	<th>Mail</th>
	<th>Attack</th>
	<th>Remove</th>
	<th>Comment</th>
	<th>Change Comment</th>
	<th>Online?</th>
		</tr>
   ';
    $q = $db->run(
        'SELECT bl.bl_COMMENT, bl_ID, u.laston, donatordays, username, userid
        FROM blacklist AS bl
        LEFT JOIN users AS u ON bl.bl_ADDED = u.userid
        WHERE bl.bl_ADDER = ?
        ORDER BY u.username',
        $userid,
    );
    foreach ($q as $r) {
        $on =
            ($r['laston'] >= (($_SERVER['REQUEST_TIME'] - 15) * 60))
                ? '<font color="green"><b>Online</b></font>'
                : '<font color="red"><b>Offline</b></font>';
        $d  = '';
        if ($r['donatordays']) {
            $r['username'] = "<font color=red>{$r['username']}</font>";
            $d             =
                "<img src='app/view/assets/images/donator.gif' alt='Donator: {$r['donatordays']} Days Left' title='Donator: {$r['donatordays']} Days Left' />";
        }
        if (empty($r['bl_COMMENT'])) {
            $r['bl_COMMENT'] = 'N/A';
        }
        echo "
		<tr>
	<td>{$r['userid']}</td>
	<td><a href='viewuser.php?u={$r['userid']}'>{$r['username']}</a> $d</td>
	<td><a href='mailbox.php?action=compose&ID={$r['userid']}'>Mail</a></td>
	<td><a href='attack.php?ID={$r['userid']}'>Attack</a></td>
	<td><a href='blacklist.php?action=remove&b={$r['bl_ID']}'>Remove</a></td>
	<td>" . strip_tags($r['bl_COMMENT'])
            . "</td> <td><a href='blacklist.php?action=ccomment&b={$r['bl_ID']}'>Change</a></td>
	<td>$on</td>
		</tr>
   ";
    }
    echo '</table>';
}

/**
 * @return void
 */
function add_enemy(): void
{
    global $db, $userid;
    $_POST['ID']      =
        (isset($_POST['ID']) && is_numeric($_POST['ID']))
            ? abs(intval($_POST['ID'])) : '';
    $_POST['comment'] =
        (isset($_POST['comment']) && is_string($_POST['comment']))
            ? strip_tags(stripslashes($_POST['comment']))
            : '';

    if ($_POST['ID']) {
        $dupe_count = $db->cell(
            'SELECT COUNT(bl_ADDER) FROM blacklist WHERE bl_ADDER = ? AND bl_ADDED = ?',
            $userid,
            $_POST['ID'],
        );
        $r          = $db->row(
            'SELECT userid, username FROM users WHERE userid = ? LIMIT 1',
            $_POST['ID'],
        );
        if ($dupe_count > 0) {

            echo 'You cannot add the same person twice.';
        } elseif ($userid == $_POST['ID']) {
            echo 'You cannot be so lonely that you have to try and add yourself.';
        } elseif (empty($r)) {
            echo "Oh no, you're trying to add a ghost.";
        } else {
            $save = function () use ($db, $userid, $r) {
                $db->insert(
                    'blacklist',
                    [
                        'bl_ADDER' => $userid,
                        'bl_ADDED' => $_POST['ID'],
                        'bl_COMMENT' => $_POST['comment'],
                    ]
                );
                $db->update(
                    'users',
                    ['enemy_count' => new EasyPlaceholder('enemy_count + 1')],
                    ['userid' => $r['userid']],
                );
            };
            $db->tryFlatTransaction($save);
            echo "{$r['username']} was added to your black list.<br />
<a href='blacklist.php'>&gt; Back</a>";
        }
    } else {
        $_GET['ID'] =
            (isset($_GET['ID']) && is_numeric($_GET['ID']))
                ? abs(intval($_GET['ID'])) : '';
        echo "
Adding an enemy!
<form action='blacklist.php?action=add' method='post'>
	Enemy's ID: <input type='text' name='ID' value='{$_GET['ID']}' /><br />
	Comment (optional): <br />
	<textarea name='comment' rows='7' cols='40'></textarea><br />
	<input type='submit' value='Add Enemy' />
</form>
        ";
    }

}

/**
 * @return void
 */
function remove_enemy(): void
{
    global $db, $userid, $h;
    $_GET['b'] =
        (isset($_GET['b']) && is_numeric($_GET['b']))
            ? abs(intval($_GET['b'])) : '';
    if (empty($_GET['b'])) {
        echo '
You didn\'t select a real enemy.<br />
&gt; <a href="blacklist.php">Back</a>
    ';
        $h->endpage();
        exit;
    }

    $r = $db->row(
        'SELECT bl_ADDED FROM blacklist WHERE bl_ID = ? AND bl_ADDER = ?',
        $_GET['b'],
        $userid,
    );
    if (empty($r)) {
        echo 'Listing doesn\'t exist.';
        $h->endpage();
        exit;
    }
    $save = function () use ($db, $userid, $r) {
        $db->delete(
            'blacklist',
            [
                'bl_ID' => $_GET['b'],
                'bl_ADDER' => $userid,
            ]
        );
        $db->update(
            'users',
            ['enemy_count' => new EasyPlaceholder('enemy_count - 1')],
            ['userid' => $r['bl_ADDED']],
        );
    };
    $db->tryFlatTransaction($save);
    echo "
Black list entry removed!<br />
<a href='blacklist.php'>&gt; Back</a>
   ";
}

/**
 * @return void
 */
function change_comment(): void
{
    global $db, $userid, $h;
    $_POST['b']       =
        (isset($_POST['b']) && is_numeric($_POST['b']))
            ? abs(intval($_POST['b'])) : '';
    $_POST['comment'] =
        (isset($_POST['comment']) && is_string($_POST['comment']))
            ? strip_tags(stripslashes($_POST['comment']))
            : '';
    if (!empty($_POST['comment']) && !empty($_POST['b'])) {
        $db->update(
            'blacklist',
            ['bl_COMMENT' => $_POST['comment']],
            [
                'bl_ID' => $_POST['b'],
                'bl_ADDER' => $userid,
            ],
        );
        echo "
Comment for enemy changed!<br />
<a href='blacklist.php'>&gt; Back</a>
   ";
    } else {
        $_GET['b'] =
            (isset($_GET['b']) && is_numeric($_GET['b']))
                ? abs(intval($_GET['b'])) : '';
        if (empty($_GET['b'])) {
            echo "
Invalid enemy.<br />
<a href='blacklist.php'>&gt; Back</a>
   ";
            $h->endpage();
            exit;
        }
        $r = $db->row(
            'SELECT bl_COMMENT FROM blacklist WHERE bl_ID = ? AND bl_ADDER = ?',
            $_GET['b'],
            $userid,
        );
        if (!empty($r)) {
            $comment = stripslashes(strip_tags($r['bl_COMMENT']));
            echo "
Changing a comment.
<form action='blacklist.php?action=ccomment' method='post'>
		<input type='hidden' name='b' value='{$_GET['b']}' /><br />
		Comment: <br />
		<textarea rows='7' cols='40' name='comment'>$comment</textarea><br />
		<input type='submit' value='Change Comment' />
</form>
    ";
        } else {
            echo 'It would be impossible to edit something which isn\'t yours.<br /> &gt; <a href="index.php">Go Home</a>';
        }
    }
}

$h->endpage();
