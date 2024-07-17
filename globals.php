<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

if (str_contains($_SERVER['PHP_SELF'], 'globals.php'))
{
    exit;
}
require __DIR__.'/vendor/autoload.php';
session_name('MCCSID');
session_start();
if (!isset($_SESSION['started']))
{
    session_regenerate_id();
    $_SESSION['started'] = true;
}
ob_start();
require __DIR__.'/lib/basic_error_handler.php';
set_error_handler('error_php');
require __DIR__.'/global_func.php';
$domain = determine_game_urlbase();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] == 0)
{
    $login_url = "https://{$domain}/login.php";
    header("Location: {$login_url}");
    exit;
}
$userid = (int)($_SESSION['userid'] ?? 0);
require __DIR__.'/header.php';

global $_CONFIG;
include __DIR__.'/config.php';
const MONO_ON = 1;
$db = ParagonIE\EasyDB\Factory::fromArray([
    'mysql:host=' . $_CONFIG['hostname'] . ';dbname=' . $_CONFIG['database'],
    $_CONFIG['username'],
    $_CONFIG['password'],
]);
$set = get_site_settings();
if ($set['use_timestamps_over_crons']) {
    define('SILENT_CRONS', true);
    require_once __DIR__ . '/crons/cronless_crons.php';
}
global $jobquery, $housequery;
$statement = match(true) {
    isset($jobquery) && $jobquery => 'SELECT u.*, us.*, j.*, jr.*
        FROM users AS u
        INNER JOIN userstats AS us ON u.userid = us.userid
        LEFT JOIN jobs AS j ON j.jID = u.job
        LEFT JOIN jobranks AS jr ON jr.jrID = u.jobrank
        WHERE u.userid = ?
        LIMIT 1',
    isset($housequery) && $housequery => 'SELECT u.*, us.*, h.*
        FROM users AS u
        INNER JOIN userstats AS us ON u.userid = us.userid
        LEFT JOIN houses AS h ON h.hWILL = u.maxwill
        WHERE u.userid = ?
        LIMIT 1',
    default => 'SELECT u.*, us.*
        FROM users AS u
        INNER JOIN userstats AS us ON u.userid = us.userid
        WHERE u.userid = ?
        LIMIT 1',
};
$ir = $db->row($statement, $userid);
set_userdata_data_types($ir);
if (empty($ir)) {
    session_unset();
    session_destroy();
    header('Location: https://' .$domain. '/login.php');
    exit;
}
if ($ir['force_logout'] > 0)
{
    $db->update(
        'users',
        ['force_logout' => 0],
        ['userid' => $userid],
    );
    session_unset();
    session_destroy();
    $login_url = "https://{$domain}/login.php";
    header("Location: {$login_url}");
    exit;
}
global $macropage;
if ($macropage && !$ir['verified'] && $set['validate_on'] == 1)
{
    $macro_url = "https://{$domain}/macro1.php?refer=$macropage";
    header("Location: {$macro_url}");
    exit;
}
check_level();
$h = new headers();
if (!isset($nohdr) || !$nohdr)
{
    $h->startheaders();
    $fm = money_formatter($ir['money']);
    $cm = money_formatter($ir['crystals'], '');
    $lv = date('F j, Y, g:i a', $ir['laston']);
    global $atkpage;
    if ($atkpage)
    {
        $h->userdata($ir, $lv, $fm, $cm, 0);
    }
    else
    {
        $h->userdata($ir, $lv, $fm, $cm);
    }
    global $menuhide;
    if (!$menuhide)
    {
        $h->menuarea();
    }
}
