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

/**
 * @param $email
 * @return bool
 */
function valid_email($email): bool
{
    return (filter_var($email, FILTER_VALIDATE_EMAIL) === $email);
}

require_once('globals_nonauth.php');
$email = isset($_POST['email']) ? stripslashes($_POST['email']) : '';
if (empty($email)) {
    die("<font color='red'>Invalid - Blank</font>");
}
if (!valid_email($email)) {
    die("<font color='red'>Invalid - Bad Format</font>");
}
$exists = $db->exists(
    'SELECT COUNT(userid) FROM users WHERE email = ?',
    $email,
);
if ($exists) {
    echo '<font color=\'red\'>Invalid - Already In Use</font>';
} else {
    echo '<font color=\'green\'>Valid</font>';
}
