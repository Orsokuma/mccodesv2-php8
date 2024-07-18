<?php
declare(strict_types=1);
/**
 * MCCodes v2 by Dabomstew & ColdBlooded
 *
 * Repository: https://github.com/davemacaulay/mccodesv2
 * License: MIT License
 */

if (str_contains($_SERVER['PHP_SELF'], 'globals_nonauth.php')) {
    exit;
}
require __DIR__ . '/vendor/autoload.php';
session_name('MCCSID');
@session_start();
if (!isset($_SESSION['started'])) {
    session_regenerate_id();
    $_SESSION['started'] = true;
}
ob_start();
require __DIR__ . '/lib/basic_error_handler.php';
set_error_handler('error_php');
global $_CONFIG;
require __DIR__ . '/config.php';
const MONO_ON = 1;
$db  = get_db();

require_once('global_func.php');
$set = get_site_settings();
