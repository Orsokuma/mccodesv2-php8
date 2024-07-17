<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyStatement;

global $ir, $h;
require_once('sglobals.php');
if (!isset($_GET['action'])) {
    $_GET['action'] = '';
}
switch ($_GET['action']) {
    case 'editnews':
        check_access('edit_newspaper');
        newspaper_form();
        break;
    case 'subnews':
        check_access('edit_newspaper');
        newspaper_submit();
        break;
    case 'givedpform':
        check_access('manage_donator_packs');
        give_dp_form();
        break;
    case 'givedpsub':
        check_access('manage_donator_packs');
        give_dp_submit();
        break;
    case 'massmailer':
        check_access('mass_mail');
        massmailer();
        break;
    default:
        echo 'Error: This script requires an action.';
        break;
}

/**
 * @return void
 */
function newspaper_form(): void
{
    global $db;
    $news = $db->cell(
        'SELECT content FROM papercontent'
    );
    $csrf = request_csrf_html('staff_editnews');
    echo "
    <h3>Editing Newspaper</h3>
    <form action='staff_special.php?action=subnews' method='post'>
    	<textarea rows='7' cols='35' name='newspaper'>" . $news
        . "</textarea>
    	<br />
    	{$csrf}
    	<input type='submit' value='Change' />
    </form>
   ";
}

/**
 * @return void
 */
function newspaper_submit(): void
{
    global $db;
    staff_csrf_stdverify('staff_editnews', 'staff_special.php?action=editnews');
    $news = strip_tags(stripslashes($_POST['newspaper']));
    /** @noinspection SqlWithoutWhere */
    $db->safeQuery(
        'UPDATE papercontent SET content = ?',
        [$news],
    );
    echo 'Newspaper updated!';
    stafflog_add('Updated game newspaper');
}

/**
 * @return void
 */
function give_dp_form(): void
{
    $csrf = request_csrf_html('staff_givedp');
    echo "
    <h3>Giving User DP</h3>
    The user will receive the benefits of one 30-day donator pack.
    <br />
    <form action='staff_special.php?action=givedpsub' method='post'>
    	User: " . user_dropdown()
        . "
    	<br />
    	<input type='radio' name='type' value='1' /> Pack 1 (Standard)
    	<br />
    	<input type='radio' name='type' value='2' /> Pack 2 (Crystals)
    	<br />
    	<input type='radio' name='type' value='3' /> Pack 3 (IQ)
    	<br />
    	<input type='radio' name='type' value='4' /> Pack 4 (5.00)
    	<br />
    	<input type='radio' name='type' value='5' /> Pack 5 (10.00)
    	<br />
    	{$csrf}
    	<input type='submit' value='Give User DP' />
    </form>
       ";
}

/**
 * @return void
 */
function give_dp_submit(): void
{
    global $db, $h;
    staff_csrf_stdverify('staff_givedp', 'staff_special.php?action=givedpform');
    $_POST['user'] =
        (isset($_POST['user']) && is_numeric($_POST['user']))
            ? abs(intval($_POST['user'])) : '';
    $_POST['type'] =
        (isset($_POST['type'])
            && in_array($_POST['type'], [1, 2, 3, 4, 5]))
            ? abs((int)$_POST['type']) : '';
    if (empty($_POST['user']) || empty($_POST['type'])) {
        echo 'Something went wrong.<br />
        &gt; <a href="staff_special.php?action=givedpform">Go Back</a>';
        $h->endpage();
        exit;
    }
    $don = 'u.userid = u.userid';
    $d   = 0;
    if ($_POST['type'] == 1) {
        $don =
            'u.money = u.money + 5000,
                 u.crystals = u.crystals + 50,
                 us.IQ = us.IQ + 50,
                 u.donatordays = u.donatordays + 30';
        $d   = 30;
    } elseif ($_POST['type'] == 2) {
        $don =
            'u.crystals = u.crystals + 100,
                 u.donatordays = u.donatordays + 30';
        $d   = 30;
    } elseif ($_POST['type'] == 3) {
        $don =
            'us.IQ = us.IQ + 120,
                 u.donatordays = u.donatordays + 30';
        $d   = 30;
    } elseif ($_POST['type'] == 4) {
        $don =
            'u.money = u.money + 15000,
                 u.crystals = u.crystals + 75,
                 us.IQ = us.IQ + 80,
                 u.donatordays = u.donatordays + 55';
        $d   = 55;
    } elseif ($_POST['type'] == 5) {
        $don =
            'u.money = u.money + 35000,
                 u.crystals = u.crystals + 160,
                 us.IQ = us.IQ + 180,
                 u.donatordays = u.donatordays + 115';
        $d   = 115;
    }
    $save = function () use ($db, $don, $d) {
        $db->safeQuery(
            'UPDATE users AS u
             INNER JOIN userstats AS us
             ON u.userid = us.userid
             SET ' . $don . '
             WHERE u.userid = ?',
            [$_POST['user']],
        );
        event_add($_POST['user'],
            "You were given one {$d}-day donator pack (Pack {$_POST['type']}) from the administration.");
        stafflog_add(
            "Gave ID {$_POST['user']} a {$d}-day donator pack (Pack {$_POST['type']})");
    };
    $db->tryFlatTransaction($save);
    echo 'User given a DP.<br />
    &gt; <a href="staff.php">Go Home</a>';
    $h->endpage();
    exit;
}

/**
 * @param array $recipients
 * @param array|null $get_users
 * @return void
 */
function populate_recipients(array &$recipients, ?array $get_users): void
{
    foreach ($get_users as $row) {
        $recipients[] = $row['userid'];
    }
}

/**
 * @return void
 */
function massmailer(): void
{
    global $db, $h;
    $_POST['text']            =
        (isset($_POST['text']))
            ? strip_tags(stripslashes($_POST['text']))
            : '';
    $_POST['recipients']      = array_key_exists('recipients', $_POST) ? $_POST['recipients'] : null;
    $_POST['recipient-roles'] = array_key_exists('recipient-roles', $_POST) && is_array($_POST['recipient-roles']) ? $_POST['recipient-roles'] : null;
    if (!empty($_POST['text'])) {
        staff_csrf_stdverify('staff_massmailer',
            'staff_special.php?action=massmailer');
        $recipients = [];
        if ($_POST['recipients'] === 'all') {
            $get_users = $db->run(
                'SELECT userid FROM users WHERE user_level > 0',
            );
            populate_recipients($recipients, $get_users);
        } elseif ($_POST['recipients'] === 'staff') {
            $get_users = $db->run(
                'SELECT userid FROM users_roles WHERE staff_role > 0',
            );
            populate_recipients($recipients, $get_users);
        } elseif ($_POST['recipients'] === 'admin') {
            $get_roles = $db->run(
                'SELECT id FROM staff_roles WHERE administrator = true',
            );
            $roles     = [];
            foreach ($get_roles as $role) {
                $roles[] = $role['id'];
            }
            $statement = EasyStatement::open()
                ->in('staff_role IN (?*)', $roles);
            $get_users = $db->run(
                'SELECT userid FROM users_roles WHERE ' . $statement,
                ...$statement->values(),
            );
            populate_recipients($recipients, $get_users);
        } elseif (!empty($_POST['recipient-roles'])) {
            $recipient_role_ids = array_unique(array_filter(array_map(function ($role) {
                return abs(intval($role));
            }, $_POST['recipient-roles'])));
            if (empty($recipient_role_ids)) {
                echo 'Invalid role(s) selected';
                $h->endpage();
                exit;
            }
            $statement   = EasyStatement::open()
                ->in('id IN (?*)', $recipient_role_ids);
            $check_roles = $db->cell(
                'SELECT COUNT(id) FROM staff_roles WHERE ' . $statement,
                ...$statement->values(),
            );
            if ((int)$check_roles !== count($_POST['recipient-roles'])) {
                echo 'Invalid role(s) selected';
                $h->endpage();
                exit;
            }
            $statement = EasyStatement::open()
                ->in('staff_role IN (?*)', $recipient_role_ids);
            $get_users = $db->run(
                'SELECT userid FROM users_roles WHERE ' . $statement . ' GROUP BY userid',
                ...$statement->values(),
            );
            populate_recipients($recipients, $get_users);
        }
        if (empty($recipients)) {
            echo 'No recipients found';
            $h->endpage();
            exit;
        }
        $uc   = [];
        $save = function () use ($db, $recipients, &$uc) {
            $subj      = 'Mass mail from Administrator';
            $send_time = time();
            foreach ($recipients as $recipient) {
                $db->insert(
                    'mail',
                    [
                        'mail_to' => $recipient,
                        'mail_time' => $send_time,
                        'mail_subject' => $subj,
                        'mail_text' => $_POST['text'],
                    ],
                );
                $uc[] = $recipient;
            }
            $statement = EasyStatement::open()
                ->in('userid IN (?*)', $uc);
            $db->safeQuery(
                'UPDATE users SET new_mail = new_mail + 1 WHERE ' . $statement,
                $statement->values(),
            );
            stafflog_add('Sent a mass mail');
        };
        $db->tryFlatTransaction($save);
        echo '
        Sent ' . count($uc)
            . ' Mails.
        <br />
        &gt; <a href="staff.php">Go Home</a>
           ';
    } else {
        $csrf      = request_csrf_html('staff_massmailer');
        $get_roles = $db->run(
            'SELECT id, name FROM staff_roles ORDER BY id',
        );
        echo "
        <b>Mass Mailer</b>
        <br />
        <form action='staff_special.php?action=massmailer' method='post'>
            Text: <br />
            <textarea name='text' rows='7' cols='40'></textarea>
            <br />
            <input type='radio' name='recipients' value='all' /> Send to all members
            <input type='radio' name='recipients' value='staff' /> Send to staff only
            <input type='radio' name='recipients' value='admin' /> Send to admins only
            <br />
            OR Send to specific staff role(s):
            <br />
            ";
        foreach ($get_roles as $role) {
            echo '
                    <label for="role-' . $role['id'] . '">
                        <input type="checkbox" name="recipient-roles[]" id="role-' . $role['id'] . '" value="' . $role['id'] . '" />
                        ' . $role['name'] . '
                    </label><br>
                ';
        }
        echo "
            <br />
            {$csrf}
            <input type='submit' value='Send' />
        </form>
           ";
    }
}

$h->endpage();
