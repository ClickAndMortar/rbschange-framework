#!/usr/bin/env php
<?php
define("WEBEDIT_HOME", getcwd());

$profile = @file_get_contents(WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'profile');
if ($profile === false || empty($profile))
{
	echo 'Profile not defined. Please define a profile in file ./profile.';
	exit(-1);
}

define('PROFILE', trim($profile));
define('FRAMEWORK_HOME', WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'framework');
define('AG_CACHE_DIR', WEBEDIT_HOME . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . PROFILE);

require_once FRAMEWORK_HOME . '/bin/bootstrap.php';
umask(0002);
$bootStrap = new c_ChangeBootStrap(WEBEDIT_HOME);
$bootStrap->setAutoloadPath(WEBEDIT_HOME."/cache/autoload");

$argv = array_slice($_SERVER['argv'], 1);
$script = new c_Changescript(__FILE__, FRAMEWORK_HOME, 'change');

require("change_script.inc");