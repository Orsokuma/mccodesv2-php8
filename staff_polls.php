<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

global $ir, $h;
require_once('sglobals.php');
check_access('manage_polls');
//This contains shop stuffs
if (!isset($_GET['action']))
{
    $_GET['action'] = '';
}
switch ($_GET['action'])
{
case 'spoll':
    startpoll();
    break;
case 'startpoll':
    startpollsub();
    break;
case 'endpoll':
    endpoll();
    break;
default:
    echo 'Error: This script requires an action.';
    break;
}

/**
 * @return void
 */
function startpoll(): void
{
    $csrf = request_csrf_html('staff_startpoll');
    echo "
        Fill out question and choices to start a poll.
        <br />
        <form action='staff_polls.php?action=startpoll' method='post'>
        	Question: <input type='text' name='question' />
        	<br />
        	Choice 1: <input type='text' name='choice1' value='' />
        	<br />
        	Choice 2: <input type='text' name='choice2' value='' />
        	<br />
        	Choice 3: <input type='text' name='choice3' value='' />
        	<br />
        	Choice 4: <input type='text' name='choice4' value='' />
        	<br />
        	Choice 5: <input type='text' name='choice5' value='' />
        	<br />
        	Choice 6: <input type='text' name='choice6' value='' />
        	<br />
        	Choice 7: <input type='text' name='choice7' value='' />
        	<br />
        	Choice 8: <input type='text' name='choice8' value='' />
        	<br />
        	Choice 9: <input type='text' name='choice9' value='' />
        	<br />
        	Choice 10: <input type='text' name='choice10' value='' />
        	<br />
        	Results hidden till end:
        		<input type='radio' name='hidden' value='1' /> Yes
        		<input type='radio' name='hidden' value='0' checked='checked'> No
        	<br />
        	{$csrf}
        	<input type='submit' value='Submit' />
        </form>
           ";
}

/**
 * @return void
 */
function startpollsub(): void
{
    global $db, $h;
    echo 'Starting new poll...';
    staff_csrf_stdverify('staff_startpoll', 'staff_polls.php?action=spoll');
    $question =
            (isset($_POST['question']))
                    ? strip_tags(stripslashes($_POST['question']))
                    : '';
    $choice1 =
            (isset($_POST['choice1']))
                    ? strip_tags(stripslashes($_POST['choice1']))
                    : '';
    $choice2 =
            (isset($_POST['choice2']))
                    ? strip_tags(stripslashes($_POST['choice2']))
                    : '';
    $choice3 =
            (isset($_POST['choice3']))
                    ? strip_tags(stripslashes($_POST['choice3']))
                    : '';
    $choice4 =
            (isset($_POST['choice4']))
                    ? strip_tags(stripslashes($_POST['choice4']))
                    : '';
    $choice5 =
            (isset($_POST['choice5']))
                    ? strip_tags(stripslashes($_POST['choice5']))
                    : '';
    $choice6 =
            (isset($_POST['choice6']))
                    ? strip_tags(stripslashes($_POST['choice6']))
                    : '';
    $choice7 =
            (isset($_POST['choice7']))
                    ? strip_tags(stripslashes($_POST['choice7']))
                    : '';
    $choice8 =
            (isset($_POST['choice8']))
                    ? strip_tags(stripslashes($_POST['choice8']))
                    : '';
    $choice9 =
            (isset($_POST['choice9']))
                    ? strip_tags(stripslashes($_POST['choice9']))
                    : '';
    $choice10 =
            (isset($_POST['choice10']))
                    ? strip_tags(stripslashes($_POST['choice10']))
                    : '';
    if (empty($question) || empty($choice1) || empty($choice2))
    {
        echo 'You must input a question and atleast two answers.<br />
        &gt; <a href="staff_polls.php?action=spoll">Go Back</a>';
        $h->endpage();
        exit;
    }
    $db->insert(
        'polls',
        [
            'active' => '1',
            'question' => $question,
            'choice1' => $choice1,
            'choice2' => $choice2,
            'choice3' => $choice3,
            'choice4' => $choice4,
            'choice5' => $choice5,
            'choice6' => $choice6,
            'choice7' => $choice7,
            'choice8' => $choice8,
            'choice9' => $choice9,
            'choice10' => $choice10,
            'hidden' => $_POST['hidden'],
        ],
    );
    echo 'New Poll Started.<br />
    &gt; <a href="staff.php">Go Home</a>';
    $h->endpage();
    exit;
}

/**
 * @return void
 */
function endpoll(): void
{
    global $db, $h;
    $_POST['poll'] =
            (isset($_POST['poll']) && is_numeric($_POST['poll']))
                    ? abs(intval($_POST['poll'])) : '';
    if (empty($_POST['poll']))
    {
        $csrf = request_csrf_html('staff_endpoll');
        echo "
        Choose a poll to close
        <br />
        <form action='staff_polls.php?action=endpoll' method='post'>
           ";
        $exists = $db->run(
            "SELECT id, question FROM polls WHERE active = '1'"
        );
        foreach ($exists as $r)
        {
            echo '
					<input type="radio" name="poll" value="' . $r['id']
                    . '" /> Poll ID ' . $r['id'] . ' - ' . $r['question']
                    . '
					<br />
   			';
        }
        echo $csrf
                . '
			<input type="submit" value="Close Selected Poll" />
		</form>
   		';
    }
    else
    {
        staff_csrf_stdverify('staff_endpoll', 'staff_polls.php?action=endpoll');
        $exists = $db->exists(
            'SELECT COUNT(id) FROM polls WHERE id = ?',
            $_POST['poll'],
        );
        if (!$exists)
        {
            echo 'Invalid poll.<br />
            &gt; <a href="staff_polls.php?action=endpoll">Go Back</a>';
            $h->endpage();
            exit;
        }
        $db->query(
            'UPDATE polls SET active = \'0\' WHERE id = ?',
            $_POST['poll'],
        );
        echo 'Poll closed.<br />
        &gt; <a href="staff.php">Go Home</a>';
        $h->endpage();
        exit;
    }
}
$h->endpage();
