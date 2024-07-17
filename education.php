<?php
declare(strict_types=1);

/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

use ParagonIE\EasyDB\EasyPlaceholder;

global $db, $ir, $userid, $h;
require_once('globals.php');
echo '<h3>Schooling</h3>';
if ($ir['course'] > 0) {
    $coud = $db->row(
        'SELECT crNAME FROM courses WHERE crID = ?',
        $ir['course']
    );
    echo "You are currently doing the {$coud['crNAME']}, you have
          {$ir['cdays']} days remaining.";
} elseif (isset($_GET['cstart'])) {
    $_GET['cstart'] = abs((int)$_GET['cstart']);
    //Verify.
    $coud = $db->row(
        'SELECT crCOST, crDAYS, crNAME FROM courses WHERE crID = ?',
        $_GET['cstart'],
    );
    if (empty($coud)) {
        echo 'You are trying to start a non-existent course!';
    } else {
        $is_complete = $db->exists(
            'SELECT COUNT(userid) FROM coursesdone WHERE userid = ? AND courseid = ?',
            $userid,
            $_GET['cstart'],
        );
        if ($ir['money'] < $coud['crCOST']) {
            echo "You don't have enough money to start this course.";
            $h->endpage();
            exit;
        }
        if ($is_complete) {
            echo 'You have already done this course.';
            $h->endpage();
            exit;
        }
        $db->update(
            'users',
            [
                'course' => $_GET['cstart'],
                'cdays' => $coud['crDAYS'],
                'money' => new EasyPlaceholder('money - ?', $coud['crCOST']),
            ],
            ['userid' => $userid],
        );
        echo "You have started the {$coud['crNAME']},
                  it will take {$coud['crDAYS']} days to complete.";
    }
} else {
    //list courses
    echo 'Here is a list of available courses.<br />';
    $q = $db->run(
        'SELECT crID, crNAME, crDESC, crCOST FROM courses'
    );
    echo "<table width='75%' cellspacing='1' class='table'>
        		<tr style='background:gray;'>
        			<th>Course</th>
        			<th>Description</th>
        			<th>Cost</th>
        			<th>Take</th>
        		</tr>";
    foreach ($q as $r) {
        $is_complete = $db->exists(
            'SELECT COUNT(userid) FROM coursesdone WHERE userid = ? AND courseid = ?',
            $userid,
            $r['crID'],
        );
        if ($is_complete) {
            $do = '<i>Done</i>';
        } else {
            $do = "<a href='education.php?cstart={$r['crID']}'>Take</a>";
        }
        echo "<tr>
            		<td>{$r['crNAME']}</td>
            		<td>{$r['crDESC']}</td>
            		<td>" . money_formatter((int)$r['crCOST'])
            . "</td>
                    <td>$do</td>
                  </tr>";
    }
    echo '</table>';
}
$h->endpage();
