<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
        // Ignore a GET request
        header('HTTP/1.1 400 Bad Request');
        exit;
    }
}
global $db;
require_once('global_func.php');
if (!is_ajax()) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}
require_once('globals_nonauth.php');
$username =
    isset($_POST['username']) ? stripslashes($_POST['username']) : '';
if (!$username) {
    die("<font color='red'>Invalid - Blank</font>");
}
if ((strlen($username) < 3)) {
    die("<font color='red'>Invalid - Too Short</font>");
}
if ((strlen($username) > 31)) {
    die("<font color='red'>Invalid - Too Long</font>");
}
$exists = $db->exists(
    'SELECT COUNT(userid) FROM users WHERE login_name = ? OR username = ?',
    $username,
    $username,
);
if ($exists) {
    echo '<font color=\'red\'>Invalid - Taken</font>';
} else {
    echo '<font color=\'green\'>Valid</font>';
}
