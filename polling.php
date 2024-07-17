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
echo '<h3>Polling Booth</h3>
Cast your vote today!<br />';
$_POST['poll']   =
    (isset($_POST['poll']) && is_numeric($_POST['poll']))
        ? abs(intval($_POST['poll'])) : '';
$_POST['choice'] =
    (isset($_POST['choice']) && is_numeric($_POST['choice']))
        ? abs(intval($_POST['choice'])) : '';
$ir['voted']     = unserialize($ir['voted']);
if (!$_POST['choice'] || !$_POST['poll']) {
    echo "&gt; <a href='polls_view.php'>View Old Polls</a>";
}
echo "<hr />\n";
if ($_POST['choice'] && $_POST['poll']) {
    if ($ir['voted'][$_POST['poll']]) {
        echo "You've already voted in this poll.<br />
		&gt; <a href='explore.php'>Back</a>";
        $h->endpage();
        exit;
    }
    $exists = $db->exists(
        "SELECT COUNT(id) FROM polls WHERE active = '1' AND id = ?",
        $_POST['poll'],
    );
    if (!$exists) {
        echo "You are trying to vote in an invalid or finished poll.<br />
  		&gt; <a href='explore.php'>Back</a>";
        $h->endpage();
        exit;
    }
    $ir['voted'][$_POST['poll']] = $_POST['choice'];
    $save                        = function () use ($db, $ir, $userid) {
        $ser = serialize($ir['voted']);
        $db->update(
            'users',
            ['voted' => $ser],
            ['userid' => $userid],
        );
        $vote_col = 'voted' . $_POST['choice'];
        $db->update(
            'polls',
            [$vote_col => new EasyPlaceholder($vote_col . ' + 1')],
            [
                'active' => '1',
                'id' => $_POST['poll'],
            ],
        );
    };
    $db->tryFlatTransaction($save);
    echo "Your vote has been cast.<br />
	&gt; <a href='polling.php'>Back To Polling Booth</a>";
} else {
    $q = $db->run(
        "SELECT * FROM polls WHERE active = '1'"
    );
    if (empty($q)) {
        echo '<b>There are no active polls at this time</b>';
    } else {
        foreach ($q as $r) {
            if ($ir['voted'][$r['id']]) {
                echo "<br />
    			<table cellspacing='1' width='75%' class='table'>
    				<tr>
    					<th>Choice</th>
    					<th>Votes</th>
    					<th width='100'>Bar</th>
    					<th>Percentage</th>
    				</tr>
    				<tr>
    					<th colspan='4'>{$r['question']} (Already Voted)</th>
    				</tr>";
                if (!$r['hidden']) {
                    for ($i = 1; $i <= 10; $i++) {
                        if ($r['choice' . $i]) {
                            $k  = 'choice' . $i;
                            $ke = 'voted' . $i;
                            if ($r['votes'] != 0) {
                                $perc = $r[$ke] / $r['votes'] * 100;
                            } else {
                                $perc = 0;
                            }
                            echo "<tr>
                        		<td>{$r[$k]}</td>
                        		<td>{$r[$ke]}</td>
                        		<td>
                        			<img src='bargreen.gif' alt='Bar' width='$perc' height='10' />
                        		</td>
                        		<td>$perc%</td>
                        	  </tr>";
                        }
                    }
                } else {
                    echo "<tr>
                		<td colspan='4' align='center'>
                			Sorry, the results of this poll are hidden until its end.
                		</td>
                	  </tr>";
                }
                $myvote = $r['choice' . $ir['voted'][$r['id']]];
                echo "<tr>
            		<th colspan='2'>Your Vote: {$myvote}</th>
            		<th colspan='2'>Total Votes: {$r['votes']}</th>
            	  </tr>
			</table>";
            } else {
                echo "<br />
            <form action='polling.php' method='post'>
				<input type='hidden' name='poll' value='{$r['id']}' />
				<table cellspacing='1' width='75%' class='table'>
					<tr>
						<th>Choice</th>
						<th>Choose</th>
					</tr>
					<tr>
						<th colspan='2'>{$r['question']} (Not Voted)</th>
					</tr>";
                for ($i = 1; $i <= 10; $i++) {
                    if ($r['choice' . $i]) {
                        $k = 'choice' . $i;
                        if ($i == 1) {
                            $c = "checked='checked'";
                        } else {
                            $c = '';
                        }
                        echo "<tr>
                    		<td>{$r[$k]}</td>
                    		<td><input type='radio' name='choice' value='$i' $c /></td>
                    	  </tr>";
                    }
                }
                echo "<tr>
            		<th colspan='2'><input type='submit' value='Vote' /></th>
            	  </tr>
			</table></form>";
            }
        }
    }
}
$h->endpage();
