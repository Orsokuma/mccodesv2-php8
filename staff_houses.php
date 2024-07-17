<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

global $ir, $h;
require_once('sglobals.php');
check_access('manage_houses');
//This contains house stuffs
if (!isset($_GET['action'])) {
    $_GET['action'] = '';
}
switch ($_GET['action']) {
    case 'addhouse':
        addhouse();
        break;
    case 'edithouse':
        edithouse();
        break;
    case 'delhouse':
        delhouse();
        break;
    default:
        echo 'Error: This script requires an action.';
        break;
}

/**
 * @return void
 */
function addhouse(): void
{
    global $db, $h;
    $price =
        (isset($_POST['price']) && is_numeric($_POST['price']))
            ? abs(intval($_POST['price'])) : '';
    $will  =
        (isset($_POST['will']) && is_numeric($_POST['will']))
            ? abs(intval($_POST['will'])) : '';
    $name  =
        (isset($_POST['name'])
            && preg_match(
                "/^[a-z0-9_]+([\\s]{1}[a-z0-9_]|[a-z0-9_])+$/i",
                $_POST['name']))
            ? strip_tags(stripslashes($_POST['name']))
            : '';
    if ($price && $will && $name) {
        staff_csrf_stdverify('staff_addhouse',
            'staff_houses.php?action=addhouse');
        $exists = $db->exists(
            'SELECT COUNT(hID) FROM houses WHERE hWILL = ?',
            $will,
        );
        if ($exists) {
            echo 'Sorry, you cannot have two houses with the same maximum will.<br />
            &gt; <a href="staff_houses.php?action=addhouse">Go Back</a>';
            $h->endpage();
            exit;
        }
        $save = function () use ($db, $price, $name, $will) {
            $db->insert(
                'houses',
                [
                    'hNAME' => $name,
                    'hWILL' => $will,
                    'hPRICE' => $price,
                ],
            );
            stafflog_add('Created House ' . $name);
        };
        $db->tryFlatTransaction($save);
        echo 'House ' . $name
            . ' added to the game.<br />
                &gt; <a href="staff.php">Go Back</a>';
        $h->endpage();
        exit;
    } else {
        $csrf = request_csrf_html('staff_addhouse');
        echo "
        <h3>Add House</h3>
        <hr />
        <form action='staff_houses.php?action=addhouse' method='post'>
        	Name: <input type='text' name='name' /><br />
        	Price: <input type='text' name='price' /><br />
        	Max Will: <input type='text' name='will' /><br />
        	{$csrf}
        	<input type='submit' value='Add House' />
        </form>
           ";
    }
}

/**
 * @return void
 */
function edit_house_select(): void
{
    $csrf = request_csrf_html('staff_edithouse1');
    echo "
        <h3>Editing a House</h3>
        <hr />
        <form action='staff_houses.php?action=edithouse' method='post'>
        	<input type='hidden' name='step' value='1' />
        	House: " . house_dropdown()
        . "
        	<br />
        	{$csrf}
        	<input type='submit' value='Edit House' />
        </form>
           ";
}

/**
 * @return void
 */
function edit_house_configure(): void
{
    global $db, $h;
    $_POST['house'] =
        (isset($_POST['house']) && is_numeric($_POST['house']))
            ? abs(intval($_POST['house'])) : 0;
    staff_csrf_stdverify('staff_edithouse1',
        'staff_houses.php?action=edithouse');
    $old = $db->row(
        'SELECT hWILL, hPRICE, hNAME FROM houses WHERE hID = ?',
        $_POST['house'],
    );
    if (empty($old)) {
        echo 'Invalid house.<br />
            &gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
        $h->endpage();
        exit;
    }
    $csrf = request_csrf_html('staff_edithouse2');
    echo "
        <h3>Editing a House</h3>
        <hr />
        <form action='staff_houses.php?action=edithouse' method='post'>
        	<input type='hidden' name='step' value='2' />
        	<input type='hidden' name='id' value='{$_POST['house']}' />
        	Name: <input type='text' name='name' value='{$old['hNAME']}' />
        	<br />
        	Price: <input type='text' name='price' value='{$old['hPRICE']}' />
        	<br />
        	Max Will: <input type='text' name='will' value='{$old['hWILL']}' />
        	<br />
        	{$csrf}
        	<input type='submit' value='Edit House' />
        </form>
           ";
}

/**
 * @return void
 */
function edit_house_do(): void
{
    global $db, $h;
    $price       =
        (isset($_POST['price']) && is_numeric($_POST['price']))
            ? abs(intval($_POST['price'])) : 0;
    $will        =
        (isset($_POST['will']) && is_numeric($_POST['will']))
            ? abs(intval($_POST['will'])) : 0;
    $_POST['id'] =
        (isset($_POST['id']) && is_numeric($_POST['id']))
            ? abs(intval($_POST['id'])) : 0;
    if (!$price || !$will || !$_POST['id']) {
        echo 'Sorry, invalid input.
            <br />&gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
        $h->endpage();
        exit;
    }
    staff_csrf_stdverify('staff_edithouse2',
        'staff_houses.php?action=edithouse');
    $dupe_check = $db->exists(
        'SELECT hID FROM houses WHERE hWILL = ? AND hID != ?',
        $will,
        $_POST['id'],
    );
    if ($dupe_check) {
        echo 'Sorry, you cannot have two houses with the same maximum will.
            <br />&gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
        $h->endpage();
        exit;
    }
    $oldwill = $db->row(
        'SELECT hWILL FROM houses WHERE hID = ?',
        $_POST['ID'],
    );
    if (empty($oldwill)) {
        echo 'Invalid house.<br />
            &gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
        $h->endpage();
        exit;
    }
    $name =
        (isset($_POST['name'])
            && preg_match(
                "/^[a-z0-9_]+([\\s]{1}[a-z0-9_]|[a-z0-9_])+$/i",
                $_POST['name']))
            ? strip_tags(stripslashes($_POST['name']))
            : '';
    if ($oldwill == 100 && $oldwill != $will) {
        echo 'Sorry, this house\'s will bar cannot be edited.<br />
            &gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
        $h->endpage();
        exit;
    }
    $save = function () use ($db, $name, $will, $price, $oldwill) {
        $db->update(
            'houses',
            [
                'hWILL' => $will,
                'hNAME' => $name,
                'hPRICE' => $price,
            ],
            ['hID' => $_POST['ID']],
        );
        $db->update(
            'users',
            [
                'maxwill' => $will,
                'will' => new EasyPlaceholder('LEAST(will, ?)', $will),
            ],
            ['maxwill' => $oldwill],
        );
        stafflog_add('Edited house ' . $name);
    };
    $db->tryFlatTransaction($save);
    echo 'House ' . $name
        . ' was edited successfully.<br />
                &gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
    $h->endpage();
    exit;
}

/**
 * @return void
 */
function edithouse(): void
{
    global $db, $h;
    if (!isset($_POST['step'])) {
        $_POST['step'] = '0';
    }
    switch ($_POST['step']) {
        case '2':
            edit_house_do();
            break;
        case '1':
            edit_house_configure();
            break;
        default:
            edit_house_select();
            break;
    }
}

/**
 * @return void
 */
function delhouse(): void
{
    global $db, $h;
    $_POST['house'] =
        (isset($_POST['house']) && is_numeric($_POST['house']))
            ? abs(intval($_POST['house'])) : '';
    if ($_POST['house']) {
        staff_csrf_stdverify('staff_delhouse',
            'staff_houses.php?action=delhouse');
        $old = $db->row(
            "SELECT hWILL, hPRICE, hID, hNAME FROM houses WHERE hID = ?",
            $_POST['house'],
        );
        if (empty($old)) {
            echo 'Invalid house.<br />
            &gt; <a href="staff_houses.php?action=edithouse">Go Back</a>';
            $h->endpage();
            exit;
        }
        if ($old['hWILL'] == 100) {
            echo 'This house cannot be deleted.<br />
            &gt; <a href="staff_houses.php?action=delhouse">Go Back</a>';
            $h->endpage();
            exit;
        }
        $save = function () use ($db, $old) {
            $db->update(
                'users',
                [
                    'money' => new EasyPlaceholder('money + ?', $old['hPRICE']),
                    'maxwill' => 100,
                    'will' => new EasyPlaceholder('LEAST(100, will)'),
                ],
                ['maxwill' => $old['hWILL']],
            );
            $db->delete(
                'houses',
                ['hID' => $old['hID']],
            );
            stafflog_add('Deleted house ' . $old['hNAME']);
        };
        $db->tryFlatTransaction($save);
        echo 'House ' . $old['hNAME']
            . ' deleted.<br />
                &gt; <a href="staff_houses.php?action=delhouse">Go Back</a>';
        $h->endpage();
        exit;
    } else {
        $csrf = request_csrf_html('staff_delhouse');
        echo "
        <h3>Delete House</h3><hr />
        Deleting a house is permanent - be sure.
        Any users that are currently living in the house you delete
        will be returned to the first house,
        and their money will be refunded.
        <form action='staff_houses.php?action=delhouse' method='post'>
        	House: " . house_dropdown()
            . "
        	<br />
        	{$csrf}
        	<input type='submit' value='Delete House' />
        </form>
           ";
    }
}

$h->endpage();
